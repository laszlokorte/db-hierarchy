<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

use App\Hierarchy\Storage\Relational\Algebra\Value\ValueInterface;

class Setter {
	public function __construct(
		private ColumnReference $column,
		private ValueInterface $value
	) {

	}
}