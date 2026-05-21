<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Windowing;

use App\Hierarchy\Storage\Relational\Algebra\Value\ValueInterface;

class ValueWindow implements WindowingInterface
{
    public function __construct(
        private ValueWindowFunction $function,
        private ValueInterface $aggregatedValue,
    ) {
    }

    public function getFunction(): ValueWindowFunction
    {
        return $this->function;
    }

    public function getValue(): ValueInterface
    {
        return $this->aggregatedValue;
    }
}
