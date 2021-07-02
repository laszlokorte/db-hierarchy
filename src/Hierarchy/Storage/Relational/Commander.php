<?php

namespace App\Hierarchy\Storage\Relational;

use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Storage\Relational\Dialect\DialectInterface;
use App\Hierarchy\Data;

use Doctrine\DBAL\Connection;

use App\Hierarchy\Storage\Relational\Algebra\Insert;
use App\Hierarchy\Storage\Relational\Algebra\Update;
use App\Hierarchy\Storage\Relational\Algebra\Delete;
use App\Hierarchy\Storage\Relational\Algebra\Value\Parameter;

class Commander {
	private const MAX_REPAIR_RETRIES = 5;

	private $noFk = false;

	public function __construct(private SchemaDefinition $schemaDef, private CommandBuilder $commandBuilder, private Connection $connection, private DialectInterface $dialect, private ColumnCoder $coder) {

	}

	private function genUUID() {
		return sprintf('%04x%04x%04x%04x%04x%04x%04x%04x',
	        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
	        mt_rand(0, 0xffff),
	        mt_rand(0, 0x0fff) | 0x4000,
	        mt_rand(0, 0x3fff) | 0x8000,
	        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
	    );
	}

	public function createNode(string $keyId, $fieldData, $scopeId, $parentId) {
		if($this->schemaDef->isKeyScoped($keyId) !== !empty($scopeId)) {
			throw new \Exception("missing scope");
		}

		if(!$this->schemaDef->isKeyReflexive($keyId) && !empty($parentId)) {
			throw new \Exception($parentId);
		}

		$idParam = new Parameter('_id');
		$scopeParam = new Parameter('_scope');
		$parentParam = new Parameter('_parent');


		if(!empty($scopeId) && !empty($parentId)) {
			$selectMoveTargetExists = $this->commandBuilder->getSelectForScopeParentCheck($keyId, $scopeParam, $parentParam);
			
			$validPositionStmt = $this->connection->prepare($this->dialect->selectToString($selectMoveTargetExists));

			$validPositionStmt->bindValue($this->dialect->parameterToString($scopeParam), $scopeId, $this->coder->getScopeColumnBindingType($keyId));
			$validPositionStmt->bindValue($this->dialect->parameterToString($parentParam), $parentId, $this->coder->getPrimaryColumnBindingType($keyId));
			$validPositionStmt->execute();

			if(!$validPositionStmt->fetchColumn()) {
				throw new \Exception("invalid position");
			}
		}

		$insert = $this->commandBuilder->getCommandForCreateNode($keyId, $idParam, $scopeParam, $parentParam);

		$this->beginTransaction();
		$stmt = $this->connection->prepare($this->dialect->insertToString($insert));

		$generatedId = null;
		switch($this->schemaDef->getKeyIdentityColumnType($keyId)) {
			case 'uuid':
				$generatedId = $this->genUUID();
				$stmt->bindValue(
					$this->dialect->parameterToString($idParam),
					$generatedId, \PDO::PARAM_STR
				);
				break;
			case 'manual':
				$generatedId = 'affecaffee';
				$stmt->bindValue(
					$this->dialect->parameterToString($idParam),
					$generatedId, \PDO::PARAM_STR
				);
				break;
		}

    	if($this->schemaDef->isKeyScoped($keyId)) {
			$stmt->bindValue(
				$this->dialect->parameterToString($scopeParam),
				$scopeId, $this->coder->getScopeColumnBindingType($keyId)
			);
		}


    	if($this->schemaDef->isKeyReflexive($keyId) && $this->schemaDef->isKeyOrdered($keyId)) {
			$stmt->bindValue(
				$this->dialect->parameterToString($parentParam),
				$parentId, $this->coder->getPrimaryColumnBindingType($keyId)
			);
		}

		foreach ($this->schemaDef->getKeyFieldIds($keyId) as $fieldId) {
			$columnData = $this->schemaDef->convertKeyFieldDataToColumnData($keyId, $fieldId, $fieldData[$fieldId] ?? null);

			foreach($this->schemaDef->getKeyFieldColumns($keyId, $fieldId) AS $ci => $column) {
				$stmt->bindValue(
					$this->dialect->parameterToString(new Parameter($column->getName())),
					$columnData[$ci]
				);
			}
		}

    	$stmt->execute();

    	if($generatedId === NULL) {
    		$newNodeId = $this->connection->lastInsertId();
    	} else {
    		$newNodeId = $generatedId;
    	}

    	if($this->schemaDef->isKeyReflexive($keyId)) {
    		$parentParam = new Parameter('_parent');
    		$childParam = new Parameter('_child');
    		$depthParam = new Parameter('_depth');
    		$scopeParam = new Parameter('_scope');

			$closureInsert = $this->commandBuilder->getCommandForClosureInsert($keyId, $scopeParam, $parentParam, $childParam, $depthParam);
			$closureStmt = $this->connection->prepare($this->dialect->insertToString($closureInsert));

			$closureStmt->bindValue($this->dialect->parameterToString($parentParam), $newNodeId, $this->coder->getPrimaryColumnBindingType($keyId));
			$closureStmt->bindValue($this->dialect->parameterToString($childParam), $newNodeId, $this->coder->getPrimaryColumnBindingType($keyId));
			$closureStmt->bindValue($this->dialect->parameterToString($depthParam), 0, \PDO::PARAM_INT);

			if($this->schemaDef->isKeyScoped($keyId)) {
				$closureStmt->bindValue(
					$this->dialect->parameterToString($scopeParam),
					$scopeId, $this->coder->getScopeColumnBindingType($keyId)
				);
			}

    		$closureStmt->execute();

    		if(!empty($parentId)) {
    			$closureInsertParent = $this->commandBuilder->getCommandForClosureParentInsert($keyId, $scopeParam, $childParam, $parentParam);
				$closureStmt = $this->connection->prepare($this->dialect->insertToString($closureInsertParent));

    			$closureStmt->bindValue($this->dialect->parameterToString($parentParam), $parentId, $this->coder->getPrimaryColumnBindingType($keyId));
				$closureStmt->bindValue($this->dialect->parameterToString($childParam), $newNodeId, $this->coder->getPrimaryColumnBindingType($keyId));

				if($this->schemaDef->isKeyScoped($keyId)) {
					$closureStmt->bindValue(
						$this->dialect->parameterToString($scopeParam),
						$scopeId, $this->coder->getScopeColumnBindingType($keyId)
					);
				}

	    		$closureStmt->execute();
    		}

    		$this->connection->prepare($this->dialect->insertToString(
    			$this->commandBuilder->getInsertForClosureRepair($keyId)
    		));
		}

    	$this->commitTransaction();

    	return $newNodeId;
	}

	public function updateNode(string $keyId, string $nodeId, $fieldData) {
		$idParam = new Parameter('_id');
		$update = $this->commandBuilder->getCommandForUpdateNode($keyId, $idParam);

		$this->beginTransaction();
		$stmt = $this->connection->prepare($this->dialect->updateToString($update));

		foreach ($this->schemaDef->getKeyFieldIds($keyId) as $fieldId) {
			$fieldType = $this->schemaDef->getKeyFieldType($keyId, $fieldId);
			$fieldOptions = $this->schemaDef->getKeyFieldOptions($keyId, $fieldId);
			$required = $this->schemaDef->isKeyFieldRequired($keyId, $fieldId);
			$columnData = $fieldType->fieldDataToColumnData($fieldId, $fieldOptions, $fieldData[$fieldId] ?? null);

			foreach($fieldType->getColumns($fieldId, $required, $fieldOptions) AS $ci => $column) {
				$stmt->bindValue(
					$this->dialect->parameterToString(new Parameter($column->getName())),
					$columnData[$ci]
				);
			}
		}

		$stmt->bindValue(
			$this->dialect->parameterToString($idParam),
			$nodeId, $this->coder->getPrimaryColumnBindingType($keyId)
		);
		$stmt->execute();

    	$this->commitTransaction();
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
						$stmtCLosure->bindValue($this->dialect->parameterToString($p), $nodeIds[$i]);
					}
					$stmtCLosure->execute();
				}

				$delete = $this->commandBuilder->getCommandForDeleteMultipleNodes($key, $nodeIdParams);

				$stmt = $this->connection->prepare($this->dialect->deleteToString($delete));

				foreach($nodeIdParams AS $i => $p) {
					$stmt->bindValue($this->dialect->parameterToString($p), $nodeIds[$i]);
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
				$stmt->bindValue($this->dialect->parameterToString($p), $nodeIds[$i], \PDO::PARAM_INT);
			}
			$stmt->execute();
			$rows = $stmt->fetchAllAssociativeIndexed();
		} else {
			$nodeIdParams = array_map(fn($n) => new Parameter($n), range(1, count($nodeIds)));
			$select = $this->commandBuilder->getSelectForCollectSelfById($keyId, $nodeIdParams);
			$stmt = $this->connection->prepare($this->dialect->selectToString($select));
			foreach($nodeIdParams AS $i => $p) {
				$stmt->bindValue($this->dialect->parameterToString($p), $nodeIds[$i], \PDO::PARAM_INT);
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
			$stmt->bindValue($this->dialect->parameterToString($p), $scopeIds[$i], \PDO::PARAM_INT);
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
				$stmt->bindValue($this->dialect->parameterToString($p), $nodeIds[$i], \PDO::PARAM_INT);
			}
			$stmt->execute();
			$result[$group][$refKey] = $stmt->fetchAllAssociativeIndexed();
		}

		return $result;
	}

	public function moveNode(string $keyId, $nodeId, $targetScopeId, $targetParentId) {
		$idParam = new Parameter('_id');
		$scopeParam = new Parameter('_scope');
		$parentParam = new Parameter('_parent');

		if($this->schemaDef->isKeyScoped($keyId) === empty($targetScopeId)) {
			throw new \Exception("missing parent");
		}

		if(!$this->schemaDef->isKeyReflexive($keyId) && !empty($targetParentId)) {
			throw new \Exception($targetParentId);
		}

		if(!empty($targetScopeId) && !empty($targetParentId)) {
			$selectMoveTargetExists = $this->commandBuilder->getSelectForScopeParentCheck($keyId, $scopeParam, $parentParam);
			
			$validPositionStmt = $this->connection->prepare($this->dialect->selectToString($selectMoveTargetExists));

			$validPositionStmt->bindValue($this->dialect->parameterToString($scopeParam), $targetScopeId, \PDO::PARAM_INT);
			$validPositionStmt->bindValue($this->dialect->parameterToString($parentParam), $targetParentId, \PDO::PARAM_INT);
			$validPositionStmt->execute();

			if(!$validPositionStmt->fetchColumn()) {
				throw new \Exception("invalid position");
			}
		}

		if($this->schemaDef->isKeyReflexive($keyId) && !empty($targetParentId)) {
			$selectMoveTargetValid = $this->commandBuilder->getSelectForMoveTargetValid($keyId, $idParam, $parentParam);

			$checkCycleStmt = $this->connection->prepare($this->dialect->selectToString($selectMoveTargetValid));

			$checkCycleStmt->bindValue($this->dialect->parameterToString($idParam), $nodeId, \PDO::PARAM_INT);
			$checkCycleStmt->bindValue($this->dialect->parameterToString($parentParam), $targetParentId, \PDO::PARAM_INT);
			$checkCycleStmt->execute();

			if($checkCycleStmt->fetchColumn()) {
				throw new \Exception("invalid position");
			}
		}


		$this->beginTransaction(true);

		if($this->schemaDef->isKeyReflexive($keyId)) {
			$deleteClosureParents = $this->commandBuilder->getDeleteForMoveClosureOldParents($keyId, $idParam);

			$deleteClosureParentsStmt = $this->connection->prepare($this->dialect->deleteToString($deleteClosureParents));

			$deleteClosureParentsStmt->bindValue($this->dialect->parameterToString($idParam), $nodeId, \PDO::PARAM_INT);
			$deleteClosureParentsStmt->execute();
		}

		if($this->schemaDef->isKeyScoped($keyId)) {
			

			if($this->schemaDef->isKeyReflexive($keyId)) {
				$updateClosureScope = $this->commandBuilder->getUpdateForMoveClosureScope($keyId, $idParam, $scopeParam);

				$updateClosureScopeStmt = $this->connection->prepare($this->dialect->updateToString($updateClosureScope));

				$updateClosureScopeStmt->bindValue($this->dialect->parameterToString($idParam), $nodeId, \PDO::PARAM_INT);
				$updateClosureScopeStmt->bindValue($this->dialect->parameterToString($scopeParam), $targetScopeId, \PDO::PARAM_INT);
				$updateClosureScopeStmt->execute();

				$updateClosureParents = $this->commandBuilder->getUpdateForMoveClosureParents($keyId, $idParam, $scopeParam);

				$updateClosureParentsStmt = $this->connection->prepare($this->dialect->updateToString($updateClosureParents));

				$updateClosureParentsStmt->bindValue($this->dialect->parameterToString($idParam), $nodeId, \PDO::PARAM_INT);
				$updateClosureParentsStmt->bindValue($this->dialect->parameterToString($scopeParam), $targetScopeId, \PDO::PARAM_INT);
				$updateClosureParentsStmt->execute();
			} else {
				$updateOwnScope = $this->commandBuilder->getUpdateForMoveOwnScope($keyId, $idParam, $scopeParam);

				$updateOwnScopeStmt = $this->connection->prepare($this->dialect->updateToString($updateOwnScope));

				$updateOwnScopeStmt->bindValue($this->dialect->parameterToString($idParam), $nodeId, \PDO::PARAM_INT);
				$updateOwnScopeStmt->bindValue($this->dialect->parameterToString($scopeParam), $targetScopeId, \PDO::PARAM_INT);
				$updateOwnScopeStmt->execute();
			}
		}

		if($this->schemaDef->isKeyReflexive($keyId)) {
			$deleteClosureParents = $this->commandBuilder->getDeleteForMoveClosureOldParents($keyId, $idParam);

			$deleteClosureParentsStmt = $this->connection->prepare($this->dialect->deleteToString($deleteClosureParents));

			$deleteClosureParentsStmt->bindValue($this->dialect->parameterToString($idParam), $nodeId, \PDO::PARAM_INT);
			$deleteClosureParentsStmt->execute();


			if($targetParentId !== $nodeId) {				
				$insertClosureParents = $this->commandBuilder->getInsertForMoveClosureParents($keyId, $idParam, $scopeParam, $parentParam);

				$insertClosureParentsStmt = $this->connection->prepare($this->dialect->insertToString($insertClosureParents));

				$insertClosureParentsStmt->bindValue($this->dialect->parameterToString($idParam), $nodeId, \PDO::PARAM_INT);
				$insertClosureParentsStmt->bindValue($this->dialect->parameterToString($parentParam), $targetParentId, \PDO::PARAM_INT);
				if($this->schemaDef->isKeyScoped($keyId)) {
					$insertClosureParentsStmt->bindValue($this->dialect->parameterToString($scopeParam), $targetScopeId, \PDO::PARAM_INT);
				}

				$insertClosureParentsStmt->execute();
			}

		}

		$this->commitTransaction();
	}

	public function orderNode(string $keyId, $nodeId, $targetPosition) {
		$idParam = new Parameter('_id');
		$orderParam = new Parameter('_order');

		if(empty($targetPosition)) {
			throw new \Exception("target position must not be empty");
		}

		$update = $this->commandBuilder->getUpdateforReorderNode($keyId, $idParam, $orderParam);

		$this->beginTransaction();
		$stmt = $this->connection->prepare($this->dialect->updateToString($update));
		$stmt->bindValue($this->dialect->parameterToString($idParam), $nodeId, \PDO::PARAM_INT);
		$stmt->bindValue($this->dialect->parameterToString($orderParam), $targetPosition, \PDO::PARAM_INT);

		$stmt->execute();
		$this->commitTransaction();
	}

	public function repairAll() {
		$this->beginTransaction();
		foreach ($this->commandBuilder->getRepairableKeys() as $key) {
			$this->repairKeyInternal($key);
		}
    	$this->commitTransaction();
	}

	public function repairKey(string $keyId) {
		$this->beginTransaction();
		$result = $this->repairKeyInternal($keyId);
    	$this->commitTransaction();

    	return $result;
	}

	private function repairKeyInternal(string $keyId) {
		$commands = $this->commandBuilder->getCommandForRepairKey($keyId);
		

		foreach ($commands as $label => $command) {
			$retriesLeft = self::MAX_REPAIR_RETRIES;

			while($retriesLeft-- > 0) {
				echo $retriesLeft;
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

				if($stmt->rowCount() < 1) {
					break;
				}

			}

			if(!$retriesLeft) {
				throw new \Exception('repair timed out');
			}
		}
	}

	private function beginTransaction($noFk = false) {
		$this->connection->beginTransaction();
	}

	private function commitTransaction() {
		$this->connection->commit();
	}
}