<?php

namespace App\Hierarchy\Schema\Definition;

class OrderDefinition {
	private $columnName;

	public function __construct(
		$columnName = null, 
		private $direction = 'ASC',
		private bool $singleton = false
	) {
		$this->columnName = $columnName ?: ($singleton ? 'singleton' : 'order');
	}

	public function getColumnName() {
		return $this->columnName;
	}

	public function getDirection() {
		return $this->direction;
	}

	public function isSingleton() {
		return $this->singleton;
	}
}