<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

use App\Hierarchy\Storage\Relational\Algebra\Value\ValueInterface;
use App\Hierarchy\Storage\Relational\Algebra\Value\ColumnReference;

class Setter {
	public function __construct(
		private ColumnReference $column,
		private ValueInterface $value
	) {
	}

	public function getColumn() {
		return $this->column;
	}

	public function getValue() {
		return $this->value;
	}
}