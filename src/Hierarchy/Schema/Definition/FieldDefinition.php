<?php

namespace App\Hierarchy\Schema\Definition;

class FieldDefinition
{
    /**
     * @param array<string,mixed> $options
     */
    public function __construct(
        private LabelDefinition $label,
        private string $typeId,
        private bool $required = false,
        private bool $unique = false,
        private array $options = [],
        private bool $visibleInCollection = true)
    {
    }

    public function getLabel(): LabelDefinition
    {
        return $this->label;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function isUnique(): bool
    {
        return $this->unique;
    }

    public function isVisibleInCollection(): bool
    {
        return $this->visibleInCollection;
    }

    /**
     * @return array<string,mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function getTypeId(): string
    {
        return $this->typeId;
    }
}
