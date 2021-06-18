<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

use App\Hierarchy\Storage\Relational\Algebra\Value\ValueInterface;

class Join {
	public function __construct(
		private TableReference $table,
		private ValueInterface $condition
	) {

	}
}