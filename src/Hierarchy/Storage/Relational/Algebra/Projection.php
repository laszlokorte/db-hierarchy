<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

use App\Hierarchy\Storage\Relational\Algebra\Value\ColumnReference;
use App\Hierarchy\Storage\Relational\Algebra\Value\ValueInterface;

class Projection
{
    public function __construct(
        private ValueInterface $value,
        private ?Identifier $alias = null,
    ) {
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getAlias()
    {
        return $this->alias;
    }

    public function getAutoName(string $fallback)
    {
        if (null !== $this->alias) {
            return $this->alias;
        }

        if ($this->value instanceof ColumnReference) {
            return $this->value->getName();
        }

        return new Identifier($fallback);
    }
}
