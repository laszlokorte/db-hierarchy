<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

use App\Hierarchy\Storage\Relational\Algebra\Value\ValueInterface;

class Order {
	public function __construct(
		private ValueInterface $value,
		private bool $ascending = true
	) {

	}
}