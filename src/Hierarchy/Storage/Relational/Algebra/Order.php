<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

use App\Hierarchy\Storage\Relational\Algebra\Value\ValueInterface;

class Order {
	public function __construct(
		private ValueInterface $value,
		private bool $ascending = true,
		private bool $nullFirst = false
	) {
	}

	public function getValue() {
		return $this->value;
	}

	public function isAscending() {
		return $this->ascending;
	}

	public function isNullFirst() {
		return $this->nullFirst;
	}
}