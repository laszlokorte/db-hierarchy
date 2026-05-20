<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Value;

use App\Hierarchy\Storage\Relational\Algebra\Select;

class ElementOf implements ValueInterface
{
    public function __construct(private ValueInterface $value, private Select|array $select)
    {
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getSelect()
    {
        return $this->select;
    }
}
