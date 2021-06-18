<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Value;

use App\Hierarchy\Storage\Relational\Algebra\TableReference;
use App\Hierarchy\Storage\Relational\Algebra\Identifier;

class ColumnReference implements ValueInterface {
	public function __construct(
		private TableReference $table,
		private Identifier $name,
	) {

	}
}