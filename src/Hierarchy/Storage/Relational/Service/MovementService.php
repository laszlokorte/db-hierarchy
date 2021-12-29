<?php

namespace App\Hierarchy\Storage\Relational\Service;

use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Storage\Relational\Dialect\DialectInterface;
use App\Hierarchy\Storage\Relational\ColumnCoder;

use App\Hierarchy\Changeset\Movement;
use App\Hierarchy\Data;

use App\Util\ResultFetcher;

use Doctrine\DBAL\Connection;

class MovementService {
	public function __construct(private SchemaDefinition $schemaDef, private MovementCommandBuilder $commandBuilder, private Connection $connection, private DialectInterface $dialect, private ColumnCoder $coder) {

	}

	public function getFreshMovement(Data\Node $node) {
		return new Movement(
			$node->getKey(),
			$node->getId(),
			$node->getScope(),
			$node->getParent(),
			null
		);
	}

	public function getValidatedMovement(Data\Node $node, ?string $targetScopeId, ?string $targetParentId) {
		// check target position

		return new Movement(
			$node->getKey(),
			$node->getId(),
			$targetScopeId,
			$targetParentId,
			[]
		);
	}

	public function findNodeMoveTargets(string $keyId, string $nodeId) {
		$groupedRows = [];

		$rootKeyIds = [];
		if($this->schemaDef->isKeyReflexive($keyId)) {
			$idParam = new Parameter('_id');
			$select = $this->commandBuilder->getSelectForFindHierarchyCousins($keyId, $idParam);


			$this->connection->beginTransaction();

			$stmt = $this->connection->prepare($this->dialect->selectToString($select));
			$stmt->bindValue($this->dialect->parameterToString($idParam), $nodeId);
			$stmtResult = $stmt->execute();



			$groupedRows[$keyId] = ResultFetcher::fetchGrouped($stmtResult);
			$rootKeyIds[] = $keyId;
		}

		if($this->schemaDef->isKeyScoped($keyId)) {
			$scope = $this->schemaDef->getKeyScopeId($keyId);

			$select = $this->commandBuilder->getSelectForFindHierarchy($scope, null, null);

			
			$this->connection->beginTransaction();
			$stmt = $this->connection->prepare($this->dialect->selectToString($select));
			$stmtResult = $stmt->execute();
			$this->connection->commit();

			$groupedRows[$scope] = ResultFetcher::fetchGrouped($stmtResult);
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
			$stmtResult = $validPositionStmt->execute();

			if(!$stmtResult->fetchOne()) {
				throw new \Exception("invalid position");
			}
		}

		if($this->schemaDef->isKeyReflexive($keyId) && !empty($targetParentId)) {
			$selectMoveTargetValid = $this->commandBuilder->getSelectForMoveTargetValid($keyId, $idParam, $parentParam);

			$checkCycleStmt = $this->connection->prepare($this->dialect->selectToString($selectMoveTargetValid));

			$checkCycleStmt->bindValue($this->dialect->parameterToString($idParam), $nodeId, $this->coder->getPrimaryColumnBindingType($keyId));
			$checkCycleStmt->bindValue($this->dialect->parameterToString($parentParam), $targetParentId, $this->coder->getPrimaryColumnBindingType($keyId));
			$stmtResult = $checkCycleStmt->execute();

			if($stmtResult->fetchOne()) {
				throw new \Exception("invalid position");
			}
		}



		$this->connection->beginTransaction();
		
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

		$this->connection->commit();
	}
}