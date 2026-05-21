<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Value;

use App\Hierarchy\Storage\Relational\Algebra\Operator\BinaryInterface;

class BinaryOperation implements ValueInterface
{
    public function __construct(
        private BinaryInterface $operator,
        private ValueInterface $leftOperand,
        private ValueInterface $rightOperand,
    ) {
    }

    public function getOperator(): BinaryInterface
    {
        return $this->operator;
    }

    public function getLeftOperand(): ValueInterface
    {
        return $this->leftOperand;
    }

    public function getRightOperand(): ValueInterface
    {
        return $this->rightOperand;
    }
}
