<?php

namespace App\Hierarchy\Schema;

class ScopeDefinition {
	private $scopeKey;
	private $columnName;
	private $unqiue = false;

	public function __construct($scopeKey, $columnName, $unique) {
		$this->scopeKey = $scopeKey;
		$this->columnName = $columnName;
		$this->unique = $unique;
	}
}