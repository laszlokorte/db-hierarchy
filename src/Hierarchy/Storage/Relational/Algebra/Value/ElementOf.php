<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Value;

use App\Hierarchy\Storage\Relational\Algebra\Select;

class ElementOf implements ValueInterface
{
    /**
     * @param Select|mixed[] $select
     */
    public function __construct(private ValueInterface $value, private Select|array $select)
    {
    }

    public function getValue(): ValueInterface
    {
        return $this->value;
    }

    public function getSelect(): Select|array
    {
        return $this->select;
    }
}
