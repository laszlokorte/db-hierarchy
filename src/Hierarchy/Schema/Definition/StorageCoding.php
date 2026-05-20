<?php

namespace App\Hierarchy\Schema\Definition;

class StorageCoding
{
    public function __construct(
        private string $type,
        private ?string $parameter = null,
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getParameter(): ?string
    {
        return $this->parameter;
    }
}
