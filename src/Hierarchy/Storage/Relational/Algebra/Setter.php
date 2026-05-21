<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

use App\Hierarchy\Storage\Relational\Algebra\Value\ColumnReference;
use App\Hierarchy\Storage\Relational\Algebra\Value\ValueInterface;

class Setter
{
    public function __construct(
        private ColumnReference $column,
        private ValueInterface $value,
    ) {
    }

    public function getColumn(): ColumnReference
    {
        return $this->column;
    }

    public function getValue(): ValueInterface
    {
        return $this->value;
    }
}
