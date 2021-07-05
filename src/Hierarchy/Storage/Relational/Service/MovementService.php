<?php

namespace App\Hierarchy\Storage\Relational\Service;

use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Storage\Relational\Dialect\DialectInterface;
use App\Hierarchy\Storage\Relational\ColumnCoder;

use Doctrine\DBAL\Connection;

class MovementService {
	public function __construct(private SchemaDefinition $schemaDef, private Connection $connection, private DialectInterface $dialect, private ColumnCoder $coder) {

	}

	public function validateMoveNode(string $keyId, string $nodeId, ?string $targetScopeId, ?string $targetParentId) {
		// check target position

		return new Validation(
			$keyId, 
			$nodeId, 
			null,
			[],
			$targetScopeId, 
			$targetParentId
		);
	}

	public function findNodeMoveTargets(string $keyId, string $nodeId) {
		$groupedRows = [];

		$rootKeyIds = [];
		if($this->schemaDef->isKeyReflexive($keyId)) {
			$idParam = new Parameter('_id');
			$select = $this->queryBuilder->getSelectForFindHierarchyCousins($keyId, $idParam);

			$this->beginTransaction();
			$stmt = $this->connection->prepare($this->dialect->selectToString($select));
			$stmt->bindValue($this->dialect->parameterToString($idParam), $nodeId);
			$stmt->execute();
	    	$this->commitTransaction();

			$groupedRows[$keyId] = $stmt->fetchAll(\PDO::FETCH_GROUP);
			$rootKeyIds[] = $keyId;
		}

		if($this->schemaDef->isKeyScoped($keyId)) {
			$scope = $this->schemaDef->getKeyScopeId($keyId);

			$select = $this->queryBuilder->getSelectForFindHierarchy($scope, null, null);

			$this->beginTransaction();
			$stmt = $this->connection->prepare($this->dialect->selectToString($select));
			$stmt->execute();
	    	$this->commitTransaction();

			$groupedRows[$scope] = $stmt->fetchAll(\PDO::FETCH_GROUP);
			$rootKeyIds[] = $scope;
		}

		return new Data\MultiTree(
			$rootKeyIds,
			$groupedRows
		);
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

			$validPositionStmt->bindValue($this->dialect->parameterToString($scopeParam), $targetScopeId, $this->coder->getScopeColumnBindingType($keyId));
			$validPositionStmt->bindValue($this->dialect->parameterToString($parentParam), $targetParentId, $this->coder->getPrimaryColumnBindingType($keyId));
			$validPositionStmt->execute();

			if(!$validPositionStmt->fetchColumn()) {
				throw new \Exception("invalid position");
			}
		}

		if($this->schemaDef->isKeyReflexive($keyId) && !empty($targetParentId)) {
			$selectMoveTargetValid = $this->commandBuilder->getSelectForMoveTargetValid($keyId, $idParam, $parentParam);

			$checkCycleStmt = $this->connection->prepare($this->dialect->selectToString($selectMoveTargetValid));

			$checkCycleStmt->bindValue($this->dialect->parameterToString($idParam), $nodeId, $this->coder->getPrimaryColumnBindingType($keyId));
			$checkCycleStmt->bindValue($this->dialect->parameterToString($parentParam), $targetParentId, $this->coder->getPrimaryColumnBindingType($keyId));
			$checkCycleStmt->execute();

			if($checkCycleStmt->fetchColumn()) {
				throw new \Exception("invalid position");
			}
		}


		$this->beginTransaction(true);

		if($this->schemaDef->isKeyReflexive($keyId)) {
			$deleteClosureParents = $this->commandBuilder->getDeleteForMoveClosureOldParents($keyId, $idParam);

			$deleteClosureParentsStmt = $this->connection->prepare($this->dialect->deleteToString($deleteClosureParents));

			$deleteClosureParentsStmt->bindValue($this->dialect->parameterToString($idParam), $nodeId, $this->coder->getPrimaryColumnBindingType($keyId));
			$deleteClosureParentsStmt->execute();
		}

		if($this->schemaDef->isKeyScoped($keyId)) {
			

			if($this->schemaDef->isKeyReflexive($keyId)) {
				$updateClosureScope = $this->commandBuilder->getUpdateForMoveClosureScope($keyId, $idParam, $scopeParam);

				$updateClosureScopeStmt = $this->connection->prepare($this->dialect->updateToString($updateClosureScope));

				$updateClosureScopeStmt->bindValue($this->dialect->parameterToString($idParam), $nodeId, $this->coder->getPrimaryColumnBindingType($keyId));
				$updateClosureScopeStmt->bindValue($this->dialect->parameterToString($scopeParam), $targetScopeId, $this->coder->getScopeColumnBindingType($keyId));
				$updateClosureScopeStmt->execute();

				$updateClosureParents = $this->commandBuilder->getUpdateForMoveClosureParents($keyId, $idParam, $scopeParam);

				$updateClosureParentsStmt = $this->connection->prepare($this->dialect->updateToString($updateClosureParents));

				$updateClosureParentsStmt->bindValue($this->dialect->parameterToString($idParam), $nodeId, $this->coder->getPrimaryColumnBindingType($keyId));
				$updateClosureParentsStmt->bindValue($this->dialect->parameterToString($scopeParam), $targetScopeId, $this->coder->getScopeColumnBindingType($keyId));
				$updateClosureParentsStmt->execute();
			} else {
				$updateOwnScope = $this->commandBuilder->getUpdateForMoveOwnScope($keyId, $idParam, $scopeParam);

				$updateOwnScopeStmt = $this->connection->prepare($this->dialect->updateToString($updateOwnScope));

				$updateOwnScopeStmt->bindValue($this->dialect->parameterToString($idParam), $nodeId, $this->coder->getPrimaryColumnBindingType($keyId));
				$updateOwnScopeStmt->bindValue($this->dialect->parameterToString($scopeParam), $targetScopeId, $this->coder->getScopeColumnBindingType($keyId));
				$updateOwnScopeStmt->execute();
			}
		}

		if($this->schemaDef->isKeyReflexive($keyId)) {
			$deleteClosureParents = $this->commandBuilder->getDeleteForMoveClosureOldParents($keyId, $idParam);

			$deleteClosureParentsStmt = $this->connection->prepare($this->dialect->deleteToString($deleteClosureParents));

			$deleteClosureParentsStmt->bindValue($this->dialect->parameterToString($idParam), $nodeId, $this->coder->getPrimaryColumnBindingType($keyId));
			$deleteClosureParentsStmt->execute();


			if($targetParentId !== $nodeId) {				
				$insertClosureParents = $this->commandBuilder->getInsertForMoveClosureParents($keyId, $idParam, $scopeParam, $parentParam);

				$insertClosureParentsStmt = $this->connection->prepare($this->dialect->insertToString($insertClosureParents));

				$insertClosureParentsStmt->bindValue($this->dialect->parameterToString($idParam), $nodeId, $this->coder->getPrimaryColumnBindingType($keyId));
				$insertClosureParentsStmt->bindValue($this->dialect->parameterToString($parentParam), $targetParentId, $this->coder->getPrimaryColumnBindingType($keyId));
				if($this->schemaDef->isKeyScoped($keyId)) {
					$insertClosureParentsStmt->bindValue($this->dialect->parameterToString($scopeParam), $targetScopeId, $this->coder->getScopeColumnBindingType($keyId));
				}

				$insertClosureParentsStmt->execute();
			}

		}

		$this->commitTransaction();
	}
}