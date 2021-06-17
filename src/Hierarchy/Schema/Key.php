<?php

namespace App\Hierarchy\Schema;

use App\Hierarchy\Schema\Definition\SchemaDefinition;

class Key {
	public function __construct(
		private SchemaDefinition $def, 
		private string $keyId
	) {
	}

	public function getId() {
		return $this->keyId;
	}

	public function hasField(string $fieldId) {
		return $this->def->keyFieldExists($this->keyId, $fieldId);
	}

	public function getField(string $fieldId) {
		return new Field($this->def, $this->keyId, $fieldId);
	}

	public function getFields() {
		return array_map([$this, 'getField'], $this->def->getKeyFieldIds($this->keyId));
	}

	public function getLabel() {
		return $this->def->getKeyLabel($this->keyId);
	}

	public function isReflexive() {
		return $this->def->isKeyReflexive($this->keyId);
	}

	public function isOrdered() {
		return $this->def->isKeyOrdered($this->keyId);
	}

	public function isScoped() {
		return $this->def->isKeyScoped($this->keyId);
	}

	public function getScopeKey() {
		return new Key($this->def, $this->def->getKeyScopeId($this->keyId));
	}

	public function getScopeChildKeys() {
		return array_map(
			fn($k) => new Key($this->def, $k),
			$this->def->getKeyIdsScopedInside($this->keyId)
		);
	}
}