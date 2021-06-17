<?php

namespace App\Hierarchy\Schema;

use App\Hierarchy\Schema\Definition\SchemaDefinition;

class Field {
	public function __construct(
		private SchemaDefinition $def, 
		private string $keyId, 
		private string $fieldId
	) {
	}

	public function getId() {
		return $this->fieldId;
	}

	public function getKey() {
		return new Key($this->def, $this->keyId);
	}

	public function getLabel() {
		return $this->def->getKeyFieldLabel($this->keyId, $this->fieldId);
	}

	public function isRequired() {
		return $this->def->isKeyFieldRequired($this->keyId, $this->fieldId);
	}

	public function isUnique() {
		return $this->def->isKeyFieldUnique($this->keyId, $this->fieldId);
	}
}