<?php

namespace App\Hierarchy\Schema\Definition;

class StorageCoding
{
    public function __construct(
        private StorageCodingType $type,
        private ?string $parameter = null,
    ) {
    }

    public function getType(): StorageCodingType
    {
        return $this->type;
    }

    public function getParameter(): ?string
    {
        return $this->parameter;
    }
}
