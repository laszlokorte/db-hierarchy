<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

use App\Hierarchy\Storage\Relational\Algebra\Value\ValueInterface;

class Delete {
	public function __construct(
		private Identifier $tableName,
		private ValueInterface $condition
	) {

	}
}