<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

use App\Hierarchy\Storage\Relational\Algebra\Value\Constant;

class TableColumn
{
    public function __construct(
        private Identifier $name,
        private string $type,
        private bool $nullable = true,
        private ?Constant $default = null,
        private bool $serial = false,
    ) {
    }

    public function getName(): Identifier
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    public function getDefault(): ?Constant
    {
        return $this->default;
    }

    public function hasDefault(): bool
    {
        return null !== $this->default;
    }

    public function isSerial(): bool
    {
        return $this->serial;
    }
}
