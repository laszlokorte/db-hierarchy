<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Value;

use App\Hierarchy\Storage\Relational\Algebra\Operator\AssociativeInterface;

class AssociativeOperation implements ValueInterface {
	public function __construct(
		private AssociativeInterface $operator,
		private array $operands 
	) {

	}

	public function getOperator() {
		return $this->operator;
	}

	public function getOperands() {
		return $this->operands;
	}
}