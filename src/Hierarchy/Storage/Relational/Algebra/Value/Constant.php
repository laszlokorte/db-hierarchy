<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Value;

class Constant implements ValueInterface
{
    public function __construct(
        private mixed $value,
    ) {
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function isNull(): bool
    {
        return null === $this->value;
    }
}
