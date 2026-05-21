<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Value;

use App\Hierarchy\Storage\Relational\Algebra\Select;

class Selection implements ValueInterface
{
    public function __construct(
        private Select $select,
    ) {
    }

    public function getSelect(): Select
    {
        return $this->select;
    }
}
