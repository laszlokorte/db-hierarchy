<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Value;

use App\Hierarchy\Storage\Relational\Algebra\Operator\UnaryInterface;

class UnaryOperation implements ValueInterface {
	public function __construct(
		private UnaryInterface $operator,
		private ValueInterface $operand
	) {

	}

	public function getOperator() {
		return $this->operator;
	}

	public function getOperand() {
		return $this->operand;
	}
}