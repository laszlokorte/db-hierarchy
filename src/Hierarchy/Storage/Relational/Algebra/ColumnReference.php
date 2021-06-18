<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

class ColumnReference {
	public function __construct(
		private TableReference $table,
		private Identifier $name,
	) {

	}
}