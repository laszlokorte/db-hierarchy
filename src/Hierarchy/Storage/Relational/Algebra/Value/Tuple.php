<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Value;

class Tuple implements ValueInterface
{
    /**
     * @param array<int,mixed> $values
     */
    public function __construct(private array $values)
    {
    }

    /**
     * @return array<int,mixed>
     */
    public function getValues(): array
    {
        return $this->values;
    }
}
