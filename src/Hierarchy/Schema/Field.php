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
		$this->def->getKeyFieldLabel($this->keyId, $this->fieldId);
	}

	public function isRequired() {
		$this->def->isKeyFieldRequired($this->keyId, $this->fieldId);
	}

	public function isUnique() {
		$this->def->isKeyFieldUnique($this->keyId, $this->fieldId);
	}
}