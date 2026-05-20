<?php

namespace App\Hierarchy\Schema\Definition;

class FieldDefinition
{
    private string $typeId;
    private bool $required = false;
    private bool $unique = false;
    private LabelDefinition $label;
    private array $options;
    private bool $visibleInCollection;
    /**
     * @param array<int,mixed> $options
     */
    public function __construct(LabelDefinition $label, string $typeId, bool $required = false, bool $unique = false, array $options = [], bool $visibleInCollection = true)
    {
        $this->label = $label;
        $this->typeId = $typeId;
        $this->required = $required;
        $this->unique = $unique;
        $this->options = $options;
        $this->visibleInCollection = $visibleInCollection;
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

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getTypeId(): string
    {
        return $this->typeId;
    }
}
