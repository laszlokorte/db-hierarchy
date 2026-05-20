<?php

namespace App\Hierarchy\Schema\Definition;

class ScopeDefinition
{
    private string $columnName;

    public function __construct(
        private string $scopeKeyId,
        ?string $columnName = null,
        private bool $isolating = false,
    ) {
        $this->columnName = $columnName ?? $scopeKeyId.'_id';
    }

    public function getScopeKeyId() : string
    {
        return $this->scopeKeyId;
    }

    public function getColumnName() : ?string
    {
        return $this->columnName;
    }

    public function isIsolating() : bool
    {
        return $this->isolating;
    }
}
