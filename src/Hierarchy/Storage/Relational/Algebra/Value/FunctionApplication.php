<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Value;

use App\Hierarchy\Storage\Relational\Algebra\Operator\FunctionInterface;

class FunctionApplication implements ValueInterface {
	public function __construct(
		private FunctionInterface $function,
		private array $arguments
	) {

	}

	public function getFunction() {
		return $this->function;
	}

	public function getArguments() {
		return $this->arguments;
	}
}