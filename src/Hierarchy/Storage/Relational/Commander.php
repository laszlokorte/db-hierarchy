<?php

namespace App\Hierarchy\Storage\Relational;

use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Storage\Relational\Dialect\DialectInterface;

use Doctrine\DBAL\Connection;

use App\Hierarchy\Storage\Relational\Algebra\Insert;
use App\Hierarchy\Storage\Relational\Algebra\Update;
use App\Hierarchy\Storage\Relational\Algebra\Delete;
use App\Hierarchy\Storage\Relational\Algebra\Value\Parameter;

class Commander {
	private const MAX_REPAIR_RETRIES = 5;

	public function __construct(private SchemaDefinition $schemaDef, private CommandBuilder $commandBuilder, private Connection $connection, private DialectInterface $dialect) {

	}

	public function createNode(string $keyId, $fieldData, $scopeId, $parentId) {
		if($this->schemaDef->isKeyScoped($keyId) !== !empty($scopeId)) {
			throw new \Exception("missing scope");
		}

		if(!$this->schemaDef->isKeyReflexive($keyId) && !empty($parentId)) {
			throw new \Exception($parentId);
		}

		$insert = $this->commandBuilder->getCommandForCreateNode($keyId);

		$this->connection->beginTransaction();
		$stmt = $this->connection->prepare($this->dialect->insertToString($insert));

    	if($this->schemaDef->isKeyScoped($keyId)) {
			$stmt->bindValue(
				$this->dialect->parameterToString(new Parameter('_scope')),
				$scopeId
			);
		}

		foreach ($this->schemaDef->getKeyFieldIds($keyId) as $fieldId) {
			$fieldType = $this->schemaDef->getKeyFieldType($keyId, $fieldId);
			$fieldOptions = $this->schemaDef->getKeyFieldOptions($keyId, $fieldId);
			$columnData = $fieldType->fieldDataToColumnData($fieldId, $fieldOptions, $fieldData[$fieldId]);

			foreach($fieldType->getColumns($fieldId, $fieldOptions) AS $ci => $column) {
				$stmt->bindValue(
					$this->dialect->parameterToString(new Parameter($column->getName())),
					$columnData[$ci]
				);
			}
		}

    	$stmt->execute();
    	$newNodeId = $this->connection->lastInsertId();

    	if($this->schemaDef->isKeyReflexive($keyId)) {
			$closureInsert = $this->commandBuilder->getCommandForClosureInsert($keyId);
			$closureStmt = $this->connection->prepare($this->dialect->insertToString($closureInsert));

			$closureStmt->bindValue($this->dialect->parameterToString(new Parameter('_parent')), $newNodeId);
			$closureStmt->bindValue($this->dialect->parameterToString(new Parameter('_child')), $newNodeId);
			$closureStmt->bindValue($this->dialect->parameterToString(new Parameter('_depth')), 0);

			if($this->schemaDef->isKeyScoped($keyId)) {
				$closureStmt->bindValue(
					$this->dialect->parameterToString(new Parameter('_scope')),
					$scopeId
				);
			}

    		$closureStmt->execute();

    		if(!empty($parentId)) {
    			$closureStmt->bindValue($this->dialect->parameterToString(new Parameter('_parent')), $parentId);
				$closureStmt->bindValue($this->dialect->parameterToString(new Parameter('_child')), $newNodeId);
				$closureStmt->bindValue($this->dialect->parameterToString(new Parameter('_depth')), 1);

	    		$closureStmt->execute();
    		}

    		$this->connection->prepare($this->dialect->insertToString(
    			$this->commandBuilder->getInsertForClosureRepair($keyId)
    		));
		}

    	$this->connection->commit();
	}

	public function updateNode(string $keyId, string $nodeId, $fieldData) {
		$idParam = new Parameter('_id');
		$update = $this->commandBuilder->getCommandForUpdateNode($keyId, $idParam);

		$this->connection->beginTransaction();
		$stmt = $this->connection->prepare($this->dialect->updateToString($update));

		foreach ($this->schemaDef->getKeyFieldIds($keyId) as $fieldId) {
			$fieldType = $this->schemaDef->getKeyFieldType($keyId, $fieldId);
			$fieldOptions = $this->schemaDef->getKeyFieldOptions($keyId, $fieldId);
			$columnData = $fieldType->fieldDataToColumnData($fieldId, $fieldOptions, $fieldData[$fieldId]);

			foreach($fieldType->getColumns($fieldId, $fieldOptions) AS $ci => $column) {
				$stmt->bindValue(
					$this->dialect->parameterToString(new Parameter($column->getName())),
					$columnData[$ci]
				);
			}
		}

		$stmt->bindValue(
			$this->dialect->parameterToString($idParam),
			$nodeId
		);
		$stmt->execute();

    	$this->connection->commit();
	}

	public function deleteNode(string $keyId, $nodeId) {
		$deletionPlan = $this->collectChildNodesByNodeIds($keyId, [$nodeId]);

		try {
			$this->connection->beginTransaction();
			foreach (array_reverse($deletionPlan) as $key => $nodeIds) {
				if(empty($nodeIds)) {
					continue;
				}
				$nodeIdParams = array_map(fn($n) => new Parameter($n), range(1, count($nodeIds)));
				$delete = $this->commandBuilder->getCommandForDeleteMultipleNodes($keyId, $nodeIdParams);

				$stmt = $this->connection->prepare($this->dialect->deleteToString($delete));

				foreach($nodeIdParams AS $i => $p) {
					$stmt->bindValue($this->dialect->parameterToString($p), $nodeIds[$i]);
				}
				$stmt->execute();
			}
			$this->connection->commit();
		} catch(\Exception $e) {
			$this->connection->rollback();
			throw $e;
		}
	}

	public function collectChildNodesByNodeIds(string $keyId, $nodeIds) {
		if($this->schemaDef->isKeyReflexive($keyId)) {
			$nodeIdParams = array_map(fn($n) => new Parameter($n), range(1, count($nodeIds)));
			$select = $this->commandBuilder->getSelectForCollectChildByIdReflexive($keyId, $nodeIdParams);
			$stmt = $this->connection->prepare($this->dialect->selectToString($select));
			foreach($nodeIdParams AS $i => $p) {
				$stmt->bindValue($this->dialect->parameterToString($p), $nodeIds[$i]);
			}
			$stmt->execute();
			$reflexiveIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);
		} else {
			$reflexiveIds = $nodeIds;
		}

		$ids = [
			$keyId => $reflexiveIds,
		];

		foreach ($this->schemaDef->getKeyIdsScopedInside($keyId) as $scopeId) {
			$ids = array_merge($ids, $this->collectChildNodesByScopeIds($scopeId, $reflexiveIds));
		}

		return $ids;
	}

	private function collectChildNodesByScopeIds(string $keyId, $scopeIds) {
		$nodeIdParams = array_map(fn($n) => new Parameter($n), range(1, count($scopeIds)));
		if($this->schemaDef->isKeyReflexive($keyId)) {
			$select = $this->commandBuilder->getSelectForCollectChildByScopeReflexive($keyId, $nodeIdParams);
		} else {
			$select = $this->commandBuilder->getSelectForCollectChildByScope($keyId, $nodeIdParams);
		}

		$stmt = $this->connection->prepare($this->dialect->selectToString($select));
		foreach($nodeIdParams AS $i => $p) {
			$stmt->bindValue($this->dialect->parameterToString($p), $scopeIds[$i]);
		}
		$stmt->execute();
		$reflexiveIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

		$ids = [
			$keyId => $reflexiveIds
		];

		foreach ($this->schemaDef->getKeyIdsScopedInside($keyId) as $scopeId) {
			$ids = array_merge($ids, $this->collectChildNodesByScopeIds($scopeId, $reflexiveIds));
		}

		return $ids;
	}

	public function moveNode(string $keyId, $nodeId, $targetScopeId, $targetParentId) {
		
	}

	public function repairAll() {
		$this->connection->beginTransaction();
		foreach ($this->commandBuilder->getRepairableKeys() as $key) {
			$this->repairKeyInternal($key);
		}
    	$this->connection->commit();
	}

	public function repairKey(string $keyId) {
		$this->connection->beginTransaction();
		$result = $this->repairKeyInternal($keyId);
    	$this->connection->commit();

    	return $result;
	}

	private function repairKeyInternal(string $keyId) {
		$retriesLeft = self::MAX_REPAIR_RETRIES;

		while($retriesLeft-- > 0) {
			$commands = $this->commandBuilder->getCommandForRepairKey($keyId);
			$affected = 0;

			foreach ($commands as $label => $command) {
				switch (get_class($command)) {
					case Insert::class:
						$stmt = $this->connection->prepare($this->dialect->insertToString($command));
						break;
					case Update::class:
						$stmt = $this->connection->prepare($this->dialect->updateToString($command));
						break;
					
					case Delete::class:
						$stmt = $this->connection->prepare($this->dialect->deleteToString($command));
						break;

					default: throw new \Exception("invalid command");
				}
				$stmt->execute();
				$affected += $stmt->rowCount();
			}

			if($affected < 1) {
				return;
			}
		}
	}
}