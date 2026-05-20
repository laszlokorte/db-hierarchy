<?php

namespace App\Hierarchy\Schema\Definition;

class LabelDefinition
{
    public function __construct(
        private string $singular,
        private ?string $plural = null,
        private ?string $description = null,
        private ?string $icon = null,
        private ?string $color = 'black',
        private string $none = 'Empty',
    ) {
        $this->plural = $plural ?? $singular.'s';
    }

    public function getSingular(): string
    {
        return $this->singular;
    }

    public function getPlural(): ?string
    {
        return $this->plural;
    }

    public function getEmpty(): string
    {
        return $this->none;
    }

    public function getString(bool $singular = true): ?string
    {
        return $singular ? $this->singular : $this->plural;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getColor(): string
    {
        return $this->color;
    }
}
