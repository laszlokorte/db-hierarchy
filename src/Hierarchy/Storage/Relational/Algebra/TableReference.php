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

    public function getName(): Identifier
    {
        return $this->table;
    }

    public function getAlias(): ?Identifier
    {
        return $this->alias;
    }

    public function getUsageName(): ?Identifier
    {
        return $this->alias ?: $this->table;
    }
}
