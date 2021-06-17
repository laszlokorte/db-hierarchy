<?php

namespace App\Hierarchy\Schema\Definition;

class OrderDefinition {
	public function __construct(
		private $columnName = 'order', 
		private $direction = 'ASC'
	) {
	}

	public function getColumnName() {
		return $this->columnName;
	}

	public function getDirection() {
		return $this->direction;
	}
}