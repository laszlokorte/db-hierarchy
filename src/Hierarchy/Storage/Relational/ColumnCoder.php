<?php

namespace App\Hierarchy\Storage\Relational;

use App\Hierarchy\Schema\Definition\ColumnDefinition;
use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Schema\Definition\StorageCodingType;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Function\Hex;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Function\Unhex;
use App\Hierarchy\Storage\Relational\Algebra\TableReference;
use App\Hierarchy\Storage\Relational\Algebra\Value\ColumnReference;
use App\Hierarchy\Storage\Relational\Algebra\Value\Constant;
use App\Hierarchy\Storage\Relational\Algebra\Value\FunctionApplication;
use App\Hierarchy\Storage\Relational\Algebra\Value\Parameter;
use App\Hierarchy\Storage\Relational\Algebra\Value\ValueInterface;
use Doctrine\DBAL\ParameterType;

class ColumnCoder
{
    public function __construct(private SchemaDefinition $schemaDef, private Naming $naming)
    {
    }

    public function wrapPrimaryKeyParameter(string $keyId, Parameter|Constant $parameter): ValueInterface
    {
        return $this->encodeColumnType($this->schemaDef->getKeyIdentityColumnType($keyId), $parameter);
    }

    public function wrapScopeParameter(string $keyId, Parameter|Constant $parameter): ValueInterface
    {
        $scopeId = $this->schemaDef->getKeyScopeId($keyId);

        if (!$scopeId) {
            return $parameter;
        }

        return $this->wrapPrimaryKeyParameter($scopeId, $parameter);
    }

    public function wrapParentParameter(string $keyId, Parameter|Constant $parameter): ValueInterface
    {
        return $this->wrapPrimaryKeyParameter($keyId, $parameter);
    }

    public function wrapOrderParameter(string $keyId, Parameter $parameter): Parameter
    {
        return $parameter;
    }

    public function wrapPrimaryColumn(string $keyId, TableReference $tableRef): ValueInterface
    {
        $columnRef = new ColumnReference($tableRef, $this->naming->nodeTablePKName($keyId));

        return $this->decodeColumnType($this->schemaDef->getKeyIdentityColumnType($keyId), $columnRef);
    }

    public function wrapScopeColumn(string $keyId, TableReference $tableRef): ValueInterface
    {
        $columnRef = new ColumnReference($tableRef, $this->naming->nodeOwnScopeColumnName($keyId));

        return $this->decodeColumnType($this->schemaDef->getKeyScopeColumnType($keyId), $columnRef);
    }

    public function wrapOrderColumn(string $keyId, TableReference $tableRef): ColumnReference
    {
        $columnRef = new ColumnReference($tableRef, $this->naming->orderColumnName($keyId));

        return $columnRef;
    }

    public function wrapColumn(ColumnDefinition $column, TableReference $tableRef): ValueInterface
    {
        if ($column->isReference()) {
            $refType = $this->schemaDef->getKeyIdentityColumnType($column->getCoding()->getTarget());

            return $this->decodeColumnType($refType, new ColumnReference($tableRef, $this->naming->fieldColumnToName($column)));
        }

        return $this->decodeColumnType($column->getCoding()->getType(), new ColumnReference($tableRef, $this->naming->fieldColumnToName($column)));
    }

    public function wrapColumnParameter(ColumnDefinition $column, Parameter|Constant $parameter): ValueInterface
    {
        if ($column->isReference()) {
            $refType = $this->schemaDef->getKeyIdentityColumnType($column->getCoding()->getTarget());

            return $this->encodeColumnType($refType, $parameter);
        }

        return $this->encodeColumnType($column->getCoding()->getType(), $parameter);
    }

    public function wrapClosureParentColumn(string $keyId, TableReference $tableRef): ValueInterface
    {
        $columnRef = new ColumnReference($tableRef, $this->naming->closureParentColumnName($keyId));

        return $this->decodeColumnType($this->schemaDef->getKeyIdentityColumnType($keyId), $columnRef);
    }

    public function wrapClosureChildColumn(string $keyId, TableReference $tableRef): ValueInterface
    {
        $columnRef = new ColumnReference($tableRef, $this->naming->closureChildColumnName($keyId));

        return $this->decodeColumnType($this->schemaDef->getKeyIdentityColumnType($keyId), $columnRef);
    }

    public function wrapClosureDepthColumn(string $keyId, TableReference $tableRef): ColumnReference
    {
        $columnRef = new ColumnReference($tableRef, $this->naming->closureTableDepthName($keyId));

        return $columnRef;
    }

    public function wrapHierarchyPrimaryColumn(string $keyId, TableReference $tableRef): ValueInterface
    {
        $columnRef = new ColumnReference($tableRef, $this->naming->hierarchyIdColumnName($keyId));

        return $this->decodeColumnType($this->schemaDef->getKeyIdentityColumnType($keyId), $columnRef);
    }

    public function wrapHierarchyScopeColumn(string $keyId, TableReference $tableRef): ValueInterface
    {
        $columnRef = new ColumnReference($tableRef, $this->naming->hierarchyScopeColumnName($keyId));

        if (!$this->schemaDef->isKeyScoped($keyId)) {
            return $columnRef;
        }

        return $this->decodeColumnType($this->schemaDef->getKeyScopeColumnType($keyId), $columnRef);
    }

    public function wrapHierarchyOrderColumn(string $keyId, TableReference $tableRef): ColumnReference
    {
        $columnRef = new ColumnReference($tableRef, $this->naming->hierarchyOrderColumnName($keyId));

        return $columnRef;
    }

    public function wrapHierarchyParentColumn(string $keyId, TableReference $tableRef): ValueInterface
    {
        $columnRef = new ColumnReference($tableRef, $this->naming->hierarchyParentColumnName($keyId));

        return $this->decodeColumnType($this->schemaDef->getKeyIdentityColumnType($keyId), $columnRef);
    }

    public function getPrimaryColumnBindingType(string $keyId): ParameterType
    {
        return $this->getColumnBindingType($this->schemaDef->getKeyIdentityColumnType($keyId));
    }

    public function getScopeColumnBindingType(string $keyId): ParameterType
    {
        return $this->getPrimaryColumnBindingType($this->schemaDef->getKeyScopeId($keyId));
    }

    public function getParentColumnBindingType(string $keyId): ParameterType
    {
        return $this->getPrimaryColumnBindingType($keyId);
    }

    public function getOrderColumnBindingType(string $keyId): StorageCodingType
    {
        return StorageCodingType::INTEGER;
    }

    public function getColumnDefinitionBindingType(ColumnDefinition $column): ParameterType
    {
        if ($column->isReference()) {
            $refType = $this->schemaDef->getKeyIdentityColumnType($column->getCoding()->getTarget());

            return $this->getColumnBindingType($refType);
        }

        return $this->getColumnBindingType($column->getCoding()->getType());
    }

    private function getColumnBindingType(StorageCodingType $type): ParameterType
    {
        switch ($type) {
            case StorageCodingType::SERIAL:
                return ParameterType::INTEGER;
            case StorageCodingType::UUID:
                return ParameterType::STRING;
            default:
                return ParameterType::STRING;
        }
    }

    public function decodeColumnType(StorageCodingType $type, ValueInterface $value): ValueInterface
    {
        switch ($type) {
            case StorageCodingType::SERIAL:
                return $value;
            case StorageCodingType::UUID:
                return new FunctionApplication(new Hex(), [$value]);
            default:
                return $value;
        }
    }

    public function encodeColumnType(StorageCodingType $type, Parameter|Constant $value): ValueInterface
    {
        if ($value instanceof Constant && $value->isNull()) {
            return $value;
        }
        switch ($type) {
            case StorageCodingType::SERIAL:
                return $value;
            case StorageCodingType::UUID:
                return new FunctionApplication(new Unhex(), [$value]);
            default:
                return $value;
        }
    }
}
