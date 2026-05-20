<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

use App\Hierarchy\Storage\Relational\Algebra\Value\ValueInterface;

class TableReference implements ValueInterface
{
    public function __construct(
        private Identifier $table,
        private ?Identifier $alias = null,
    ) {
    }

    public function getName()
    {
        return $this->table;
    }

    public function getAlias()
    {
        return $this->alias;
    }

    public function getUsageName()
    {
        return $this->alias ?: $this->table;
    }
}
