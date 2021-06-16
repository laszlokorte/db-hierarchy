<?php

namespace App\Hierarchy\Schema;

class ScopeDefinition {
	private $scopeKey;
	private $columnName;

	public function __construct($scopeKey, $columnName) {
		$this->scopeKey = $scopeKey;
		$this->columnName = $columnName;
	}
}