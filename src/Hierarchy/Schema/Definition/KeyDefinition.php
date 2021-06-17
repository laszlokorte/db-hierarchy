<?php

namespace App\Hierarchy\Schema\Definition;

class KeyDefinition {
	private $idColumn = 'id';
	private $templateString = '';

	public function __construct(
		private string $tableName,
		private LabelDefinition $label, 
		private ?ScopeDefinition $scope = NULL, 
		private ?ReflexivityDefinition $reflexivity = NULL, 
		private ?OrderDefinition $order = NULL, 
		private array $fields = []
	) {
	}

	public function fieldExists($fieldId) {
		return array_key_exists($fieldId, $this->fields);
	}

	public function getFieldIds() {
		return array_keys($this->fields);
	}

	public function isOrdered() {
		return $this->order !== NULL;
	}

	public function getOrderColumnName() {
		return $this->order->getColumnName();
	}

	public function getOrderDirection() {
		return $this->order->getDirection();
	}

	public function isScoped() {
		return $this->scope !== NULL;
	}

	public function isScopedUnique() {
		return $this->scope->isUnique();
	}

	public function getScopeKeyId() {
		return $this->scope->getScopeKeyId();
	}

	public function getScopeColumnName() {
		return $this->scope->getColumnName();
	}

	public function isScopedInside($keyId) {
		return $this->isScoped() && $this->scope->getScopeKeyId() === $keyId;
	}

	public function isReflexive() {
		return $this->reflexivity !== NULL;
	}

	public function getReflexivityTableName() {
		return $this->reflexivity->deriveTableName($this->tableName);
	}

	public function getReflexivityParentColumn() {
		return $this->reflexivity->getParentColumnName();
	}

	public function getReflexivityChildColumn() {
		return $this->reflexivity->getChildColumnName();
	}

	public function getTableName() {
		return $this->tableName;
	}

	public function getIdColumnName() {
		return $this->idColumn;
	}

	public function getLabel() {
		return $this->thisLabel;
	}

	public function getFieldLabel($fieldId) {
		return $this->fields[$fieldId]->getLabel();
	}

	public function isFieldRequired($fieldId) {
		return $this->fields[$fieldId]->isRequired();
	}

	public function isFieldUnique($fieldId) {
		return $this->fields[$fieldId]->isUnique();
	}

	public function getFieldTypeId($fieldId) {
		return $this->fields[$fieldId]->getTypeId();
	}

	public function getFieldOptions($fieldId) {
		return $this->fields[$fieldId]->getOptions();
	}
}