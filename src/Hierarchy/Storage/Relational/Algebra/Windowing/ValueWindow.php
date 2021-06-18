<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Windowing;

use App\Hierarchy\Storage\Relational\Algebra\Value\ValueInterface;

class ValueWindow implements WindowingInterface {
	public function __construct(
		private ValueWindowFunction $function,
		private ValueInterface $aggregatedValue
	) {
	}

	public function getFunction() {
		return $this->function;
	}

	public function getValue() {
		return $this->aggregatedValue;
	}
}