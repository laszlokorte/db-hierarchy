<?php

namespace App\Hierarchy\Storage\Relational\Service;

use App\Hierarchy\Data\Validation;

use App\Hierarchy\Storage\Relational\Dialect\DialectInterface;
use App\Hierarchy\Storage\Relational\Algebra\Value\Parameter;
use App\Hierarchy\Schema\Definition\SchemaDefinition;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

class ValidationServer {
	public function __construct(
		private SchemaDefinition $schemaDef, 
		private ValidationCommandBuilder $commandBuilder, 
		private Connection $connection, 
		private DialectInterface $dialect
	) {

	}

	public function validateCreateNode(string $keyId, array $fieldData, ?string $scopeId, ?string $parentId) {
		
		$errors = [];

		$this->validateRequiredField($errors, $keyId, $fieldData);
		$this->validateNodePosition($errors, $keyId, $scopeId, $parentId);
		$this->validateUniquenessForNew($errors, $keyId, $fieldData, $scopeId, $parentId);


		return new Validation(
			$keyId, 
			null, 
			$fieldData,
			$errors, 
			$scopeId, 
			$parentId
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

	private function validateNodePosition(array &$errors, string $keyId, ?string $scopeId, ?string $parentId) {
		if($this->schemaDef->isKeyScoped($keyId) !== !empty($scopeId)) {
			$errors['_scope'][] = 'missing scope';
		}

		if(!$this->schemaDef->isKeyReflexive($keyId) && !empty($parentId)) {
			$errors['_parent'][] = 'parent id not expected';
		}

		$scopeParam = new Parameter('_scope');
		$parentParam = new Parameter('_parent');


		if(!empty($scopeId) && !empty($parentId)) {
			$selectMoveTargetExists = $this->commandBuilder->getSelectForScopeParentCheck($keyId, $scopeParam, $parentParam);
			
			$validPositionStmt = $this->connection->prepare($this->dialect->selectToString($selectMoveTargetExists));

			$validPositionStmt->bindValue($this->dialect->parameterToString($scopeParam), $scopeId, ParameterType::INTEGER);
			$validPositionStmt->bindValue($this->dialect->parameterToString($parentParam), $parentId, ParameterType::INTEGER);
			$stmtResult = $validPositionStmt->execute();

			if(!$stmtResult->fetchOne()) {
				$errors['_parent'][] = 'parent and scope not matching';
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
				$scopeId, ParameterType::INTEGER
			);
		}

    	if($this->schemaDef->isKeyReflexive($keyId)) {
			$stmt->bindValue(
				$this->dialect->parameterToString($parentParam),
				$parentId, ParameterType::INTEGER
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

		$stmtResult = $stmt->execute();
		$result = $stmtResult->fetch();

		if($result) {
			foreach ($fieldsToCheck as $fieldId => $params) {
				if($result[$fieldId]) {
					$errors[$fieldId][] = 'not unique'; 
				}
			}
		}
	}

	public function validateUpdateNode(string $keyId, string $nodeId, array $fieldData) {
		// check empty fields
		// check unique fields != self
		$errors = [];

		$this->validateUniquenessForEdit($errors, $keyId, $nodeId, $fieldData);
		$this->validateRequiredField($errors, $keyId, $fieldData);

		return new Validation(
			$keyId, 
			$nodeId, 
			$fieldData,
			$errors,
		);
	}

	private function validateUniquenessForEdit(&$errors, $keyId, $nodeId, $fieldData) {

		$idParam = new Parameter('_id');

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

		$select = $this->commandBuilder->getSelectForUniquenessCheckEdit($keyId, $idParam, $fieldsToCheck);
		$stmt = $this->connection->prepare($this->dialect->selectToString($select));

    	$stmt->bindValue(
			$this->dialect->parameterToString($idParam),
			$nodeId, ParameterType::INTEGER
		);

		foreach ($fieldsToCheck as $fieldId => $params) {
			foreach($params AS $i => $param) {
				$stmt->bindValue(
					$this->dialect->parameterToString($param),
					$valuesToCheck[$fieldId][$i]
				);
			}
		}

		$stmtResult = $stmt->execute();
		$result = $stmtResult->fetch();

		if($result) {
			foreach ($fieldsToCheck as $fieldId => $params) {
				if($result[$fieldId]) {
					$errors[$fieldId][] = 'not unique'; 
				}
			}
		}
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

	public function validateOrderNode(string $keyId, string $nodeId, $targetPosition) {
		$scopeId = null; 
		$parentId = null;

		// check order

		return new Validation(
			$keyId, 
			$nodeId, 
			null,
			[],
			$scopeId, 
			$parentId
		);
	}
}