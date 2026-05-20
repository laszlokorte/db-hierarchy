<?php

namespace App\Hierarchy\Storage\Relational;

use App\Hierarchy\Schema\Definition\ColumnDefinition;
use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Storage\Relational\Algebra\Identifier;

class Naming
{
    public function __construct(private SchemaDefinition $schemaDef)
    {
    }

    public function hierarchyViewName(string $keyId): Identifier
    {
        return new Identifier(sprintf('_%s_hierarchy', $this->schemaDef->getKeyTableName($keyId)));
    }

    public function closureInvalidViewName(string $keyId): Identifier
    {
        return new Identifier(sprintf('_%s_invalid', $this->schemaDef->getKeyReflexivityTableName($keyId)));
    }

    public function closureMissingReasonColumn(string $keyId): Identifier
    {
        return new Identifier('_reason');
    }

    public function closureInvalidIdColumn(string $keyId): Identifier
    {
        return new Identifier('_id');
    }

    public function closureInvalidParentColumn(string $keyId): Identifier
    {
        return new Identifier('_parent');
    }

    public function closureInvalidChildColumn(string $keyId): Identifier
    {
        return new Identifier('_child');
    }

    public function closureInvalidDepthColumn(string $keyId): Identifier
    {
        return new Identifier('_depth');
    }

    public function closureMissingViewName(string $keyId): Identifier
    {
        return new Identifier(sprintf('_%s_missing', $this->schemaDef->getKeyReflexivityTableName($keyId)));
    }

    public function normalizedOrderViewName(string $keyId): Identifier
    {
        return new Identifier(sprintf('_%s_normalized_order', $this->schemaDef->getKeyTableName($keyId)));
    }

    public function normalizedOrderStoredColumnName(string $keyId): Identifier
    {
        return new Identifier('_stored_order');
    }

    public function normalizedOrderNormalizedColumnName(string $keyId): Identifier
    {
        return new Identifier('_normalized_order');
    }

    public function normalizedOrderIdColumnName(string $keyId): Identifier
    {
        return new Identifier('_id');
    }

    public function normalizedOrderParentColumnName(string $keyId): Identifier
    {
        return new Identifier('_parent');
    }

    public function normalizedOrderScopeColumnName(string $keyId): Identifier
    {
        return new Identifier('_scope');
    }

    public function closureMissingIdColumn(string $keyId): Identifier
    {
        return new Identifier('_id');
    }

    public function closureMissingParentColumn(string $keyId): Identifier
    {
        return new Identifier('_parent');
    }

    public function closureMissingChildColumn(string $keyId): Identifier
    {
        return new Identifier('_child');
    }

    public function closureMissingDepthColumn(string $keyId): Identifier
    {
        return new Identifier('_depth');
    }

    public function closureParentColumnName(string $keyId): Identifier
    {
        $parentColumn = $this->schemaDef->getKeyReflexivityParentColumn($keyId);

        return new Identifier($parentColumn->getName());
    }

    public function closureChildColumnName(string $keyId): Identifier
    {
        $childColumn = $this->schemaDef->getKeyReflexivityChildColumn($keyId);

        return new Identifier($childColumn->getName());
    }

    public function fieldColumnToName(ColumnDefinition $columnDefinition): Identifier
    {
        return new Identifier($columnDefinition->getName());
    }

    public function nodeTableName(string $keyId): Identifier
    {
        return new Identifier($this->schemaDef->getKeyTableName($keyId));
    }

    public function nodeTablePKName(string $keyId): Identifier
    {
        $pkColumn = $this->schemaDef->getKeyIdentityColumn($keyId);

        return new Identifier($pkColumn->getName());
    }

    public function scopeTablename(string $keyId) : Identifier
    {
        return $this->nodeTableName($this->schemaDef->getKeyScopeId($keyId));
    }

    public function scopeTablePKName(string $keyId) : Identifier
    {
        return $this->nodeTablePKName($this->schemaDef->getKeyScopeId($keyId));
    }

    public function nodeOwnScopeColumnName(string $keyId) : Identifier
    {
        return $this->fieldColumnToName($this->schemaDef->getKeyScopeColumn($keyId));
    }

    public function nodeOwnIsolationColumnName(string $keyId) : Identifier
    {
        return $this->fieldColumnToName($this->schemaDef->getKeyIsolationColumn($keyId));
    }


    public function closureTableName(string $keyId): Identifier
    {
        return new Identifier($this->schemaDef->getKeyReflexivityTableName($keyId));
    }

    public function orderColumnName(string $keyId): Identifier
    {
        return new Identifier($this->schemaDef->getKeyOrderColumn($keyId)->getName());
    }

    public function closureTablePkName(string $keyId): Identifier
    {
        return new Identifier('id');
    }

    public function closureTableDepthName(string $keyId): Identifier
    {
        return new Identifier('depth');
    }

    public function hierarchyIdColumnName(string $keyId): Identifier
    {
        return new Identifier('_id');
    }

    public function hierarchyOrderColumnName(string $keyId): Identifier
    {
        return new Identifier('_order');
    }

    public function hierarchyParentColumnName(string $keyId): Identifier
    {
        return new Identifier('_parent');
    }

    public function hierarchyScopeColumnName(string $keyId): Identifier
    {
        return new Identifier('_scope');
    }
}
