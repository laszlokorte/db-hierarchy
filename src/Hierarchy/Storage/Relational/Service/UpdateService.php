<?php

namespace App\Hierarchy\Storage\Relational\Service;

use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Storage\Relational\Dialect\DialectInterface;
use App\Hierarchy\Storage\Relational\ColumnCoder;
use App\Hierarchy\Storage\Relational\Algebra\Value\Parameter;

use App\Hierarchy\Changeset\Update;
use App\Hierarchy\Data\Node;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

class UpdateService {
	public function __construct(private SchemaDefinition $schemaDef, private UpdateCommandBuilder $commandBuilder, private Connection $connection, private DialectInterface $dialect, private ColumnCoder $coder) {

	}

	public function getFreshUpdate(Node $node) {
		return new Update(
			$node->getKey(), 
			$node->getId(),
			[],
			$node->getColumnValues(),
			null
		);
	}

	public function getValidatedUpdate(Node $node, array $fieldData) {
		// check empty fields
		// check unique fields != self
		$keyId = $node->getKey();
		$nodeId = $node->getId();
		$errors = [];

		$this->validateUniquenessForEdit($errors, $keyId, $nodeId, $fieldData);
		$this->validateRequiredField($errors, $keyId, $fieldData);

		$newColumnData = [];

		foreach ($this->schemaDef->getKeyFieldIds($keyId) as $fieldId) {
			$columnData = $this->schemaDef->convertKeyFieldDataToColumnData($keyId, $fieldId, $fieldData[$fieldId] ?? null);

			foreach($this->schemaDef->getKeyFieldColumns($keyId, $fieldId) AS $ci => $column) {
				$newColumnData[$column->getName()] = $columnData[$ci];
			}
		}

		return new Update(
			$keyId, 
			$nodeId,
			$newColumnData,
			$node->getColumnValues(),
			$errors
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

	public function updateNode(string $keyId, string $nodeId, $fieldData) {
		$idParam = new Parameter('_id');
		$update = $this->commandBuilder->getCommandForUpdateNode($keyId, $idParam);

		if(!$update->isEmpty()) {
			$this->connection->beginTransaction();
			
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

	    	$this->connection->commit();
		}
	}
}