<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

use App\Hierarchy\Storage\Relational\Algebra\Value\ValueInterface;

class Insert {
	public function __construct(
		private Identifier $table,
		private array $columns,
		private array $rows
	) {

	}
}