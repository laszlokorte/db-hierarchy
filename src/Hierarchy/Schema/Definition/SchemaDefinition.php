<?php

namespace App\Hierarchy\Schema\Definition;

class SchemaDefinition {

	public function __construct(
		private LabelDefinition $label, 
		private array $keys, 
		private array $fieldTypes
	) {
	}

	public function getSchemaLabel() {
		return $this->label;
	}

	public function getRootScopeKeyIds() {
		return array_filter(array_keys($this->keys), [$this, 'isKeyScoped']);
	}

	public function getAllKeyIds() {
		return array_keys($this->keys);
	}

	public function getKeyIdsScopedInside($keyId) {
		return array_filter(array_keys($this->keys, fn($k) => $this->isKeyScopedInside($k, $keyId)));
	}

	public function keyExists($keyId) {
		return array_key_exists($keyId, $this->keys);
	}

	public function getKeyLabel($keyId) {
		return $this->keys[$keyId]->getLabel();
	}

	public function isKeyOrdered($keyId) {
		return $this->keys[$keyId]->isOrdered();
	}

	public function getKeyOrderColumn($keyId) {
		return $this->keys[$keyId]->getOrderColumnName();
	}

	public function getKeyOrderDirection($keyId) {
		return $this->keys[$keyId]->getOrderDirection();
	}

	public function isKeyScoped($keyId) {
		return $this->keys[$keyId]->isScoped();
	}

	public function isKeyScopedInside($keyId, $scopeKeyId) {
		return $this->keys[$keyId]->isScopedInside($scopeKeyId);
	}

	public function isKeyScopedUnique($keyId) {
		$this->keys[$keyId]->isScopedUnique();
	}

	public function getKeyScopeId($keyId) {
		return $this->keys[$keyId]->getScopeKeyId();
	}

	public function isKeyReflexive($keyId) {
		return $this->keys[$keyId]->isReflexive();
	}

	public function getKeyReflexivityTable($keyId) {
		return $this->keys[$keyId]->getReflexivityTableName();
	}

	public function getKeyReflexivityParentColumn($keyId) {
		return $this->keys[$keyId]->getReflexivityParentColumn();
	}

	public function getKeyReflexivityChildColumn($keyId) {
		return $this->keys[$keyId]->getReflexivityChildColumn();
	}

	public function fieldTypeExists($fieldTypeId) {
		return array_key_exists($fieldTypeId, $this->fieldTypes);
	}

	public function keyFieldExists($keyId, $fieldId) {
		return $this->keyExists($keyId) && $this->keys[$keyId]->fieldExists($fieldId);
	}

	public function getKeyFieldIds($keyId) {
		return $this->keys[$keyId]->getFieldIds();
	}

	public function getKeyFieldLabel($keyId, $fieldId) {
		return $this->keys[$keyId]->getFieldLabel($fieldId);
	}

	public function isKeyFieldRequired($keyId, $fieldId) {
		return $this->keys[$keyId]->isFieldRequired($fieldId);
	}

	public function isKeyFieldUnique($keyId, $fieldId) {
		return $this->keys[$keyId]->isFieldUnique($fieldId);
	}

	public function getKeyFieldTypeId($keyId, $fieldId) {
		return $this->keys[$keyId]->getFieldTypeId($fieldId);
	}

	public function getKeyFieldOptions($keyId, $fieldId) {
		return $this->keys[$keyId]->getFieldOptions($fieldId);
	}

	public function getKeyFieldType($keyId, $fieldId) {
		$fieldTypeId = $this->getKeyFieldTypeId($keyId, $fieldId);

		return $this->fieldTypes[$fieldTypeId];
	}

	public function getKeyIdentityColumn($keyId) {
		return $this->keys[$keyId]->getIdColumnName();
	}

	public function getKeyScopeColumn($keyId) {
		return $this->keys[$keyId]->getScopeColumnName();
	}

	public function getKeyTable($keyId) {
		return $this->keys[$keyId]->getTableName();
	}
}