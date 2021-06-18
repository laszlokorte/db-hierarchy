<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Value;

class Constant implements ValueInterface {
	public function __construct(
		private mixed $value
	) {

	}

	public function getValue() {
		return $this->value;
	}
}