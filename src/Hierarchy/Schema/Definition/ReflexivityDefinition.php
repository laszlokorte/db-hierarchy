<?php

namespace App\Hierarchy\Schema\Definition;

class ReflexivityDefinition
{
    public function __construct(
        private string $parentColumn = 'parent_id',
        private string $childColumn = 'child_id',
        private string $depth = 'depth',
        private ?string $closureTable = null,
    ) {
    }

    public function getParentColumnName()
    {
        return $this->parentColumn;
    }

    public function getChildColumnName()
    {
        return $this->childColumn;
    }

    public function deriveTableName($baseTableName)
    {
        return $this->closureTable ?? sprintf('%s_closure', $baseTableName);
    }
}
