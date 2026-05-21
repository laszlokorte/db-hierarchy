<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Value;

use App\Hierarchy\Storage\Relational\Algebra\Windowing\WindowingInterface;

class Windowing implements ValueInterface
{
    /**
     * @param array<int,mixed> $partionValues
     * @param array<int,mixed> $orders
     */
    public function __construct(
        private WindowingInterface $windowFunction,
        private array $partionValues = [],
        private array $orders = [],
    ) {
    }

    public function getWindowFunction(): WindowingInterface
    {
        return $this->windowFunction;
    }

    public function getPartionValues(): array
    {
        return $this->partionValues;
    }

    public function getOrders(): array
    {
        return $this->orders;
    }
}
