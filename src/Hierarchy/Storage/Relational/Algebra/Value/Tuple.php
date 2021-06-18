<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Value;

class Tuple implements ValueInterface {
	public function __construct(private array $values) {

	}
}