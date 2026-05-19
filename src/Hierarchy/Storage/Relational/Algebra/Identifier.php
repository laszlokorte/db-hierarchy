<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

class Identifier {
	public function __construct(
		private string $string
	) {

	}

	public function getString() {
		return $this->string;
	}
}