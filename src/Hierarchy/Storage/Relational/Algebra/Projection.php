<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

use App\Hierarchy\Storage\Relational\Algebra\Value\ValueInterface;

class Projection {
	public function __construct(
		private ValueInterface $value,
		private ?string $alias = NULL
	) {

	}
}