<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Value;

use App\Hierarchy\Storage\Relational\Algebra\Windowing\WindowingInterface;

class Windowing implements ValueInterface {
	public function __construct(
		private WindowingInterface $windowFunction,
		private array $partionValues = [],
		private array $orders = []
	) {

	}

	public function getWindowFunction() {
		return $this->windowFunction;
	}

	public function getPartionValues() {
		return $this->partionValues;
	}

	public function getOrders() {
		return $this->orders;
	}
}