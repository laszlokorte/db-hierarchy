<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

use App\Hierarchy\Storage\Relational\Algebra\Value\ValueInterface;

class Update {
	public function __construct(
		private TableReference $table,
		private array $setters,
		private ?ValueInterface $condition = NULL,
		private ?Select $selection = NULL 
	) {
	}

	public function getTable() {
		return $this->table;
	}

	public function getSetters() {
		return $this->setters;
	}

	public function getCondition() {
		return $this->condition;
	}

	public function getSelect() {
		return $this->selection;
	}

	public function isEmpty() {
		return empty($this->setters);
	}
}