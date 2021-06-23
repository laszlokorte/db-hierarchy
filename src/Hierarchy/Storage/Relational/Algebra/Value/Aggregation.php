<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Value;

use App\Hierarchy\Storage\Relational\Algebra\Aggregation\AggregationInterface;
use App\Hierarchy\Storage\Relational\Algebra\Value\ValueInterface;

class Aggregation implements ValueInterface {
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