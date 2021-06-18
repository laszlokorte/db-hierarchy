<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Windowing;

use App\Hierarchy\Storage\Relational\Algebra\Aggregation\AggregationInterface;
use App\Hierarchy\Storage\Relational\Algebra\Value\ValueInterface;

class AggregationWindow implements WindowingInterface {
	public function __construct(
		private AggregationInterface $aggregation,
		private ValueInterface $aggregatedValue
	) {
	}

	public function getAggregation() {
		return $this->aggregation;
	}

	public function getValue() {
		return $this->aggregatedValue;
	}
}