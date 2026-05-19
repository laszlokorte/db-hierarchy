<?php

namespace App\Hierarchy\Schema\Definition;

class KeyDefinition {
	private $templateString = '';

	public function __construct(
		private StorageDefinition $storage,
		private LabelDefinition $label, 
		private ?ScopeDefinition $scope, 
		private ?ReflexivityDefinition $reflexivity, 
		private ?OrderDefinition $order, 
		private array $fields,
		private SummaryDefinition $summary
	) {
		if(array_diff($summary->getFieldIds(), array_keys($fields))) {
			throw new \Exception(sprintf('unknown fields in key summary: %s', implode(', ', array_diff($summary->getFieldIds(), array_keys($fields)))));
		}
	}

	public function fieldExists($fieldId) {
		return array_key_exists($fieldId, $this->fields);
	}

	public function getFieldIds() {
		return array_keys($this->fields);
	}

	public function isOrdered() {
		return $this->order !== NULL && !$this->order->isSingleton();
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

	public function isScopeIsolating() {
		return $this->scope->isIsolating();
	}

	public function isSingleton() {
		return $this->order !== NULL && $this->order->isSingleton();
	}

	public function getScopeKeyId() {
		return $this->isScoped() ? $this->scope->getScopeKeyId() : null;
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

	public function isAtomic() {
		return count($this->fields) < 2;
	}

	public function getReflexivityTableName() {
		return $this->reflexivity->deriveTableName($this->getTableName());
	}

	public function getReflexivityParentColumnName() {
		return $this->reflexivity->getParentColumnName();
	}

	public function getReflexivityChildColumnName() {
		return $this->reflexivity->getChildColumnName();
	}

	public function getTableName() {
		return $this->storage->getTableName();
	}

	public function getIdColumnName() {
		return $this->storage->getIdColumnName();
	}

	public function getIdColumnType() {
		return $this->storage->getIdColumnType();
	}

	public function getIdColumn() {
		return $this->storage->getIdColumn();
	}

	public function getLabel() {
		return $this->label;
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

	public function isFieldVisibleInCollection($fieldId) {
		return $this->fields[$fieldId]->isVisibleInCollection();
	}

	public function getFieldTypeId($fieldId) {
		return $this->fields[$fieldId]->getTypeId();
	}

	public function getFieldOptions($fieldId) {
		return $this->fields[$fieldId]->getOptions();
	}

	public function getSummary() {
		return $this->summary ?? new SummaryDefinition();
	}
}