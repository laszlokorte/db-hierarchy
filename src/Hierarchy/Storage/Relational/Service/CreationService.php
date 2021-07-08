<?php

namespace App\Hierarchy\Storage\Relational\Service;

use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Storage\Relational\Dialect\DialectInterface;
use App\Hierarchy\Storage\Relational\ColumnCoder;
use App\Hierarchy\Storage\Relational\Algebra\Value\Parameter;

use App\Hierarchy\Changeset\Creation;
use App\Hierarchy\Data\Node;

use Doctrine\DBAL\Connection;

class CreationService {
	public function __construct(private SchemaDefinition $schemaDef, private CreationCommandBuilder $commandBuilder, private Connection $connection, private DialectInterface $dialect, private ColumnCoder $coder) {

	}

	public function getFreshCreation(string $keyId, ?Node $superNode = null) {
		if($superNode === null) {
			$scopeId = null;
			$parentId = null;
		} elseif($superNode->getKey() === $keyId) {
			$scopeId = $superNode->getScope();
			$parentId = $superNode->getId();
		} else {
			$scopeId = $superNode->getId();
			$parentId = null;
		}

		return new Creation(
			$keyId, 
			$scopeId, 
			$parentId, 
			[],
			null
		);
	}

	public function getValidatedCreation(string $keyId, array $fieldData, ?string $scopeId, ?string $parentId) {
		$fieldErrors = [];
		$scopeErrors = [];
		$parentErrors = [];

		$this->validateRequiredField($fieldErrors, $keyId, $fieldData);
		$this->validateNodePosition($scopeErrors, $parentErrors, $keyId, $scopeId, $parentId);
		$this->validateUniquenessForNew($fieldErrors, $keyId, $fieldData, $scopeId, $parentId);

		return new Creation(
			$keyId, 
			$scopeId, 
			$parentId, 
			[],
			$fieldErrors,
			$scopeErrors,
			$parentErrors
		);
	}

	private function validateRequiredField(&$errors, $keyId, $fieldData) {
		foreach ($this->schemaDef->getKeyFieldIds($keyId) as $fieldId) {
			if(!$this->schemaDef->isKeyFieldRequired($keyId,  $fieldId)) {
				continue;
			}

			$fieldsToCheck[$fieldId] = [];
			$valuesToCheck[$fieldId] = [];

			$columnData = $this->schemaDef->convertKeyFieldDataToColumnData($keyId, $fieldId, $fieldData[$fieldId] ?? null);

			if(empty(array_filter($columnData, fn($d) => $d !== '' && $d !== null))) {
				$errors[$fieldId][] = 'is required';
			}
		}
	}

	private function validateNodePosition(array &$scopeErrors, array &$parentErrors, string $keyId, ?string $scopeId, ?string $parentId) {
		if($this->schemaDef->isKeyScoped($keyId) !== !empty($scopeId)) {
			$scopeErrors[] = 'is required';
		}

		if(!$this->schemaDef->isKeyReflexive($keyId) && !empty($parentId)) {
			$parentErrors[] = 'is not expected';
		}

		$scopeParam = new Parameter('_scope');
		$parentParam = new Parameter('_parent');


		if(!empty($scopeId) && !empty($parentId)) {
			$selectMoveTargetExists = $this->commandBuilder->getSelectForScopeParentCheck($keyId, $scopeParam, $parentParam);
			
			$validPositionStmt = $this->connection->prepare($this->dialect->selectToString($selectMoveTargetExists));

			$validPositionStmt->bindValue($this->dialect->parameterToString($scopeParam), $scopeId, \PDO::PARAM_INT);
			$validPositionStmt->bindValue($this->dialect->parameterToString($parentParam), $parentId, \PDO::PARAM_INT);
			$validPositionStmt->execute();

			if(!$validPositionStmt->fetchColumn()) {
				$parentErrors[] = 'is not matching';
			}
		}
	}

	private function validateUniquenessForNew(&$errors, $keyId, $fieldData, $scopeId, $parentId) {

		$scopeParam = new Parameter('_scope');
		$parentParam = new Parameter('_parent');

		$fieldsToCheck = [];
		$valuesToCheck = [];

		foreach ($this->schemaDef->getKeyFieldIds($keyId) as $fieldId) {
			if(!$this->schemaDef->isKeyFieldUnique($keyId,  $fieldId)) {
				continue;
			}
			$columnData = $this->schemaDef->convertKeyFieldDataToColumnData($keyId, $fieldId, $fieldData[$fieldId] ?? null);

			if(empty(array_filter($columnData))) {
				continue;
			}

			$fieldsToCheck[$fieldId] = [];
			$valuesToCheck[$fieldId] = [];


			foreach($this->schemaDef->getKeyFieldColumns($keyId, $fieldId) AS $ci => $column) {
				$fieldsToCheck[$fieldId][] = new Parameter($column->getName());
				$valuesToCheck[$fieldId][] = $columnData[$ci];
			}
		}

		$select = $this->commandBuilder->getSelectForUniquenessCheckNew($keyId, $scopeParam, $parentParam, $fieldsToCheck);
		$stmt = $this->connection->prepare($this->dialect->selectToString($select));

    	if($this->schemaDef->isKeyScoped($keyId)) {
			$stmt->bindValue(
				$this->dialect->parameterToString($scopeParam),
				$scopeId, \PDO::PARAM_INT
			);
		}

    	if($this->schemaDef->isKeyReflexive($keyId)) {
			$stmt->bindValue(
				$this->dialect->parameterToString($parentParam),
				$parentId, \PDO::PARAM_INT
			);
		}

		foreach ($fieldsToCheck as $fieldId => $params) {
			foreach($params AS $i => $param) {
				$stmt->bindValue(
					$this->dialect->parameterToString($param),
					$valuesToCheck[$fieldId][$i]
				);
			}
		}

		$stmt->execute();
		$result = $stmt->fetch();

		if($result) {
			foreach ($fieldsToCheck as $fieldId => $params) {
				if($result[$fieldId]) {
					$errors[$fieldId][] = 'not unique'; 
				}
			}
		}
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

		$this->connection->beginTransaction();
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

    	$this->connection->commit();

    	return $newNodeId;
	}
}