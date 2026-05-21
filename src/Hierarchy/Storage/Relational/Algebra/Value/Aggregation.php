<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Value;

use App\Hierarchy\Storage\Relational\Algebra\Aggregation\AggregationInterface;

class Aggregation implements ValueInterface
{
    public function __construct(
        private AggregationInterface $aggregation,
        private ValueInterface $aggregatedValue,
    ) {
    }

    public function getAggregation(): AggregationInterface
    {
        return $this->aggregation;
    }

    public function getValue(): ValueInterface
    {
        return $this->aggregatedValue;
    }
}
