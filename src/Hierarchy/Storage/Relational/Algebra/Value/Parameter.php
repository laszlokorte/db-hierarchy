<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Value;

class Parameter implements ValueInterface {
	public function __construct(private string $name) {

	}

	public function getName() {
		return $this->name;
	}
}