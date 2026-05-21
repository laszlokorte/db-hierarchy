<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Windowing\Value;

use App\Hierarchy\Storage\Relational\Algebra\Value\ValueInterface;
use App\Hierarchy\Storage\Relational\Algebra\Windowing\ValueWindowFunction;

class Lead implements ValueWindowFunction
{
    public function __construct(private int $offset = 1, private ?ValueInterface $default = null)
    {
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getDefault(): ?ValueInterface
    {
        return $this->default;
    }
}
