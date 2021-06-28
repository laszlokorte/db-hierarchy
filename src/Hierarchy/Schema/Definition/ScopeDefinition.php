<?php

namespace App\Hierarchy\Schema\Definition;

class ScopeDefinition {
	private string $columnName;

	public function __construct(
		private string $scopeKeyId, 
		?string $columnName = NULL, 
		private bool $unique = FALSE
	) {
		$this->columnName = $columnName ?? $scopeKeyId . '_id';
	}

	public function getScopeKeyId() {
		return $this->scopeKeyId;
	}

	public function isUnique() {
		return $this->unique;
	}

	public function getColumnName() {
		return $this->columnName;
	}
}