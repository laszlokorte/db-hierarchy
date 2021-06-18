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

	public function getAllKeys() {
		return array_map([$this, 'getKey'], $this->def->getAllKeyIds());
	}

	public function getAllHierarchies() {
		return array_map([$this, 'getHierarchy'], $this->def->getAllKeyIdsTopological());
	}

	public function getHierarchy($keyId) {
		return new Hierarchy($this->def, $keyId);
	}

	public function getDiagnosis() {
		return new Diagnosis($this->def);
	}

	public function treeNodes($data) {
		return array_map(fn($key) => new KeyTree($key, $data), $this->getRootKeys());
	}
}