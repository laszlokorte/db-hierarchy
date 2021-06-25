<?php

namespace App\Hierarchy\Schema\Definition;

use App\Hierarchy\Schema\Definition\ColumnDefinition;

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
		return array_filter(array_keys($this->keys), [$this, 'isKeyRoot']);
	}

	public function getScopedKeyIds() {
		return array_filter(array_keys($this->keys), [$this, 'isKeyScoped']);
	}

	public function getReflexiveKeyIds() {
		return array_filter(array_keys($this->keys), [$this, 'isKeyReflexive']);
	}

	public function getOrderedKeyIds() {
		return array_filter(array_keys($this->keys), [$this, 'isKeyOrdered']);
	}

	public function getAllKeyIds() {
		return array_keys($this->keys);
	}

	public function getAllKeyIdsTopological() {
		$keys = [];
		$roots = [null];

		while(!empty($roots)) {
			$r = array_pop($roots);
			$keys[] = $r;

			foreach($this->keys AS $key => $definition) {
				if($definition->getScopeKeyId() === $r) {
					$roots[] = $key;
				}
			}
		}

		if(count($keys) < count($this->keys)) {
			throw new Exception("cyclic hierarchy");
		}

		array_shift($keys);

		return $keys;
	}

	public function getKeyIdsScopedInside($keyId) {
		return array_filter(array_keys($this->keys), fn($k) => $this->isKeyScopedInside($k, $keyId));
	}

	public function getKeyIdsScopedInsideAndReflexiveSelf($keyId) {
		return array_filter(array_keys($this->keys), fn($k) => $this->isKeyScopedInsideOrReflexiveSelf($k, $keyId));
	}

	public function getKeyScopePath($keyId) {
		$scopeIds = [];

		$currentKey = $this->getKeyScopeId($keyId);

		while($currentKey) {
			$scopeIds[] = $currentKey;
			$currentKey = $this->getKeyScopeId($currentKey);
		}

		return $scopeIds;
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
		return new ColumnDefinition($this->keys[$keyId]->getOrderColumnName(), new StorageCoding(StorageCoding::INTEGER), false, 0);
	}

	public function getKeyOrderDirection($keyId) {
		return $this->keys[$keyId]->getOrderDirection();
	}

	public function isKeyScoped($keyId) {
		return $this->keys[$keyId]->isScoped();
	}

	public function isKeyRoot($keyId) {
		return !$this->isKeyScoped($keyId);
	}

	public function isKeyScopedInside($keyId, $scopeKeyId) {
		return $this->keys[$keyId]->isScopedInside($scopeKeyId);
	}

	public function isKeyScopedInsideOrReflexiveSelf($keyId, $scopeKeyId) {
		if($keyId === $scopeKeyId) {
			return $this->isKeyReflexive($keyId);
		}
		return $this->keys[$keyId]->isScopedInside($scopeKeyId);
	}

	public function isKeyScopedUnique($keyId) {
		return $this->keys[$keyId]->isScopedUnique();
	}

	public function getKeyScopeId($keyId) {
		return $this->keys[$keyId]->getScopeKeyId();
	}

	public function isKeyReflexive($keyId) {
		return $this->keys[$keyId]->isReflexive();
	}

	public function isKeyNested($keyId) {
		return $this->keys[$keyId]->isReflexive() || $this->keys[$keyId]->isScoped();
	}

	public function getKeyReflexivityTableName($keyId) {
		return $this->keys[$keyId]->getReflexivityTableName();
	}

	public function getKeyReflexivityParentColumn($keyId) {
		return $this->getKeyIdentityColumn($keyId)->deriveSameWithName($this->keys[$keyId]->getReflexivityParentColumnName());
	}

	public function getKeyReflexivityChildColumn($keyId) {
		return $this->getKeyIdentityColumn($keyId)->deriveSameWithName($this->keys[$keyId]->getReflexivityChildColumnName());
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

	public function getKeyFieldOption($keyId, $fieldId, $optionId) {
		return $this->keys[$keyId]->getFieldOption($fieldId, $optionId);
	}

	public function getKeyFieldType($keyId, $fieldId) {
		$fieldTypeId = $this->getKeyFieldTypeId($keyId, $fieldId);

		return $this->fieldTypes[$fieldTypeId];
	}

	public function getKeyIdentityColumnName($keyId) {
		return $this->keys[$keyId]->getIdColumnName();
	}

	public function getKeyIdentityColumn($keyId) {
		return $this->keys[$keyId]->getIdColumn();
	}

	public function getKeyScopeColumnName($keyId) {
		return $this->keys[$keyId]->getScopeColumnName();
	}

	public function getKeyScopeColumn($keyId) {
		return $this->getKeyIdentityColumn($this->getKeyScopeId($keyId))
			->deriveSameWithName($this->getKeyScopeColumnName($keyId));
	}

	public function getKeyTableName($keyId) {
		return $this->keys[$keyId]->getTableName();
	}
}