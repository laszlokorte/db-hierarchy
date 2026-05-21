<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Value;

use App\Hierarchy\Storage\Relational\Algebra\Operator\AssociativeInterface;

class AssociativeOperation implements ValueInterface
{
    /**
     * @param array<int,mixed> $operands
     */
    public function __construct(
        private AssociativeInterface $operator,
        private array $operands,
    ) {
    }

    public function getOperator(): AssociativeInterface
    {
        return $this->operator;
    }

    /**
     * @return array<int,mixed>
     */
    public function getOperands(): array
    {
        return $this->operands;
    }
}
