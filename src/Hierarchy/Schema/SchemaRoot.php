<?php

namespace App\Hierarchy\Schema;

use App\Hierarchy\Schema\Definition\SchemaDefinition;

class SchemaRoot {
	public function __construct(
		private SchemaDefinition $def
	) {
	}

	public function getLabel() {
		return $this->def->getSchemaLabel();
	}

	public function hasKey(string $keyId) {
		return $this->def->keyExists($keyId);
	}

	public function getKey(string $keyId) {
		return new Key($this->def, $keyId);
	}

	public function getRootKeys() {
		return array_map([$this, 'getKey'], $this->def->getRootScopeKeyIds());
	}

	public function getAllBackingTables() {
		return array_map([$this, 'getBackingTable'], $this->def->getAllKeyIds());
	}

	public function getBackingTable($keyId) {
		return new BackingTable($this->def, $keyId);
	}
}