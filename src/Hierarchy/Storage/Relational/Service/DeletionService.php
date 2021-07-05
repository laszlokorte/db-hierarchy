<?php

namespace App\Hierarchy\Storage\Relational\Service;

use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Storage\Relational\Dialect\DialectInterface;
use App\Hierarchy\Storage\Relational\ColumnCoder;

use Doctrine\DBAL\Connection;

class DeletionService {
	public function __construct(private SchemaDefinition $schemaDef, private Connection $connection, private DialectInterface $dialect, private ColumnCoder $coder) {

	}

	public function validateDeleteNode(string $keyId, string $nodeId) {
		$scopeId = null; 
		$parentId = null;

		// check deletion plan

		return new Validation(
			$keyId, 
			$nodeId, 
			null,
			[],
			$scopeId, 
			$parentId
		);
	}

	public function deleteNode(string $keyId, $nodeId) {
		$deletionPlan = $this->getDeletionPlan($keyId, $nodeId);

		if(!$deletionPlan['blockers']->isEmpty()) {
			throw new Exception\DeletionBlockedException("can not delete");
		}

		try {
			$this->beginTransaction();
			foreach ($deletionPlan['willDelete']->getKeys() as $key) {
				$nodeIds = $deletionPlan['willDelete']->getNodeIdsFor($key);
				if(empty($nodeIds)) {
					continue;
				}
				$nodeIdParams = array_map(fn($n) => new Parameter($n), range(1, count($nodeIds)));

				if($this->schemaDef->isKeyReflexive($key)) {
					$deleteClosure = $this->commandBuilder->getCommandForDeleteMultipleNodesClosure($key, $nodeIdParams);

					$stmtCLosure = $this->connection->prepare($this->dialect->deleteToString($deleteClosure));

					foreach($nodeIdParams AS $i => $p) {
						$stmtCLosure->bindValue($this->dialect->parameterToString($p), $nodeIds[$i], $this->coder->getPrimaryColumnBindingType($keyId));
					}
					$stmtCLosure->execute();
				}

				$delete = $this->commandBuilder->getCommandForDeleteMultipleNodes($key, $nodeIdParams);

				$stmt = $this->connection->prepare($this->dialect->deleteToString($delete));

				foreach($nodeIdParams AS $i => $p) {
					$stmt->bindValue($this->dialect->parameterToString($p), $nodeIds[$i], $this->coder->getPrimaryColumnBindingType($keyId));
				}
				$stmt->execute();
			}
			$this->commitTransaction();
		} catch(\Exception $e) {
			$this->connection->rollback();
			throw $e;
		}
	}

	public function getDeletionPlan($keyId, $nodeId) {
		$willDelete = $this->collectChildNodesByNodeIds($keyId, [$nodeId]);

        $referenced = $this->collectReferencedNodesByIds($keyId, [$nodeId]);
		$willDelete = array_merge($willDelete, array_filter($referenced['leafs']));
        $blockers = $referenced['inner'];

        foreach ($willDelete as $willkey => $rows) {
			$willIds = array_keys($rows);
			$referenced = $this->collectReferencedNodesByIds($willkey, $willIds);

			$willDelete = array_merge($willDelete, array_filter($referenced['leafs']));
            $blockers = array_merge($blockers, $referenced['inner']);
        }

        return [
        	'willDelete' => new Data\MultiCollection(null, null, array_reverse($willDelete), null, null),
        	'blockers' => new Data\MultiCollection(null, null, array_filter($blockers), null, null),
        ];
	}

	private function collectChildNodesByNodeIds(string $keyId, $nodeIds) {
		if(empty($nodeIds)) {
			return [];
		}
		if($this->schemaDef->isKeyReflexive($keyId)) {
			$nodeIdParams = array_map(fn($n) => new Parameter($n), range(1, count($nodeIds)));
			$select = $this->commandBuilder->getSelectForCollectChildByIdReflexive($keyId, $nodeIdParams);
			$stmt = $this->connection->prepare($this->dialect->selectToString($select));
			foreach($nodeIdParams AS $i => $p) {
				$stmt->bindValue($this->dialect->parameterToString($p), $nodeIds[$i], $this->coder->getPrimaryColumnBindingType($keyId));
			}
			$stmt->execute();
			$rows = $stmt->fetchAllAssociativeIndexed();
		} else {
			$nodeIdParams = array_map(fn($n) => new Parameter($n), range(1, count($nodeIds)));
			$select = $this->commandBuilder->getSelectForCollectSelfById($keyId, $nodeIdParams);
			$stmt = $this->connection->prepare($this->dialect->selectToString($select));
			foreach($nodeIdParams AS $i => $p) {
				$stmt->bindValue($this->dialect->parameterToString($p), $nodeIds[$i],$this->coder->getPrimaryColumnBindingType($keyId));
			}
			$stmt->execute();
			$rows = $stmt->fetchAllAssociativeIndexed();
		}

		if(empty($rows)) {
			return [];
		}

		$ids = [
			$keyId => $rows,
		];

		$reflexiveIds = array_keys($rows);

		foreach ($this->schemaDef->getKeyIdsScopedInside($keyId) as $scopeId) {
			$ids = array_merge($ids, $this->collectChildNodesByScopeIds($scopeId, $reflexiveIds));
		}

		return $ids;
	}

	private function collectChildNodesByScopeIds(string $keyId, $scopeIds) {
		if(empty($scopeIds)) {
			return [];
		}
		$nodeIdParams = array_map(fn($n) => new Parameter($n), range(1, count($scopeIds)));
		if($this->schemaDef->isKeyReflexive($keyId)) {
			$select = $this->commandBuilder->getSelectForCollectChildByScopeReflexive($keyId, $nodeIdParams);
		} else {
			$select = $this->commandBuilder->getSelectForCollectChildByScope($keyId, $nodeIdParams);
		}

		$stmt = $this->connection->prepare($this->dialect->selectToString($select));
		foreach($nodeIdParams AS $i => $p) {
			$stmt->bindValue($this->dialect->parameterToString($p), $scopeIds[$i], $this->coder->getScopeColumnBindingType($keyId));
		}
		$stmt->execute();
		$rows = $stmt->fetchAllAssociativeIndexed();

		if(empty($rows)) {
			return [];
		}

		$ids = [
			$keyId => $rows
		];

		$reflexiveIds = array_keys($rows);

		foreach ($this->schemaDef->getKeyIdsScopedInside($keyId) as $scopeId) {
			$ids = array_merge($ids, $this->collectChildNodesByScopeIds($scopeId, $reflexiveIds));
		}

		return $ids;
	}

	private function collectReferencedNodesByIds($keyId, $nodeIds) {
		$result = [
			'leafs' => [],
			'inner' => [],
		];

		if(empty($nodeIds)) {
			return $result;
		}

		$nodeIdParams = array_map(fn($n) => new Parameter($n), range(1, count($nodeIds)));

		foreach ($this->schemaDef->getAllKeyIds() as $refKey) {
			$columns = $this->schemaDef->getReferencingKeyColumns($keyId, $refKey);
			if(empty($columns)) {
				continue;
			}

			if($this->schemaDef->isKeyLeaf($refKey)) {
				$group = 'leafs';
			} else {
				$group = 'inner';
			}

			$select = $this->commandBuilder->getSelectForReferencedNodes($refKey, $columns, $nodeIdParams);

			$stmt = $this->connection->prepare($this->dialect->selectToString($select));
			foreach($nodeIdParams AS $i => $p) {
				$stmt->bindValue($this->dialect->parameterToString($p), $nodeIds[$i], $this->coder->getPrimaryColumnBindingType($keyId));
			}
			$stmt->execute();
			$result[$group][$refKey] = $stmt->fetchAllAssociativeIndexed();
		}

		return $result;
	}
}