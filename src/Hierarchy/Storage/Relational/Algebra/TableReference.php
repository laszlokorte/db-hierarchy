<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

class TableReference {
	public function __construct(
		private Identifier $table,
		private ?Identifier $alias = null
	) {

	}
}