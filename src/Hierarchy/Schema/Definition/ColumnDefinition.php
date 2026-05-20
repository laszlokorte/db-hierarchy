<?php

namespace App\Hierarchy\Schema\Definition;

class ColumnDefinition
{
    public function __construct(
        private string $name,
        private StorageCoding|ReferenceCoding $coding,
        private ?bool $nullable = false,
        private ?string $default = null,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCoding(): StorageCoding|ReferenceCoding
    {
        return $this->coding;
    }

    public function isReference(): bool
    {
        return $this->coding instanceof ReferenceCoding;
    }

    public function isNullable(): ?bool
    {
        return $this->nullable;
    }

    public function hasDefault(): bool
    {
        return null !== $this->default;
    }

    public function getDefault(): ?string
    {
        return $this->default;
    }

    public function deriveSameWithName(string $columnName, bool $keepSerial = false): ColumnDefinition
    {
        return new self($columnName, $this->coding, $this->nullable, $this->default);
    }

    public function isReferencing(string $keyId): bool
    {
        return $this->isReference() && $this->coding->isReferencing($keyId);
    }
}
