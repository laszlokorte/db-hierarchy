<?php

namespace App\Hierarchy\Schema;

class OrderDefinition {
	private $columnName;
	private $direction;

	public function __construct($columnName, $direction) {
		$this->columnName = $columnName;
		$this->direction = $direction;
	}
}