<?php

namespace App\Hierarchy\Schema;

use App\Hierarchy\Schema\Definition\LabelDefinition;
use App\Hierarchy\Schema\Definition\SchemaDefinition;

class Hierarchy
{
    public function __construct(
        private SchemaDefinition $def,
        private string $slug,
    ) {
    }

    public function getLabel(): LabelDefinition
    {
        return $this->def->getSchemaLabel();
    }

    public function hasKey(string $keyId): bool
    {
        return $this->def->keyExists($keyId);
    }

    public function getKey(string $keyId): Key
    {
        return new Key($this->def, $keyId);
    }

    public function getRootKeys(): array
    {
        return array_map([$this, 'getKey'], $this->def->getRootScopeKeyIds());
    }

    public function getAllKeys(): array
    {
        return array_map([$this, 'getKey'], $this->def->getAllKeyIds());
    }

    public function getAllKeyIdsTopological(): array
    {
        return $this->def->getAllKeyIdsTopological();
    }

    public function getAllHierarchies(): array
    {
        return array_map([$this, 'getHierarchy'], $this->def->getAllKeyIdsTopological());
    }

    public function getHierarchy(string $keyId): Hierarchy
    {
        return new Hierarchy($this->def, $keyId);
    }

    public function getSlug(): string
    {
        return $this->slug;
    }
}
