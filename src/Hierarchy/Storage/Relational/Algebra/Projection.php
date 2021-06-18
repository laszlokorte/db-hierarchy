<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

use App\Hierarchy\Storage\Relational\Algebra\Value\ValueInterface;

class Projection {
	public function __construct(
		private ValueInterface $value,
		private ?Identifier $alias = NULL
	) {
	}

	public function getValue() {
		return $this->value;
	}

	public function getAlias() {
		return $this->alias;
	}
}