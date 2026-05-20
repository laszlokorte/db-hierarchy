<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Windowing\Value;

use App\Hierarchy\Storage\Relational\Algebra\Windowing\ValueWindowFunction;

class Lead implements ValueWindowFunction
{
    public function __construct(private int $offset = 1, private ?ValueInterface $default = null)
    {
    }

    public function getOffset()
    {
        return $this->offset;
    }

    public function getDefault()
    {
        return $this->default;
    }
}
