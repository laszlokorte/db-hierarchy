<?php

namespace App\Hierarchy\Storage\Relational\Service;

use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Storage\Relational\Algebra\Delete;
use App\Hierarchy\Storage\Relational\Algebra\Identifier;
use App\Hierarchy\Storage\Relational\Algebra\Join;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Comparison\Equal;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Logic\Disjunction;
use App\Hierarchy\Storage\Relational\Algebra\Order;
use App\Hierarchy\Storage\Relational\Algebra\Projection;
use App\Hierarchy\Storage\Relational\Algebra\Select;
use App\Hierarchy\Storage\Relational\Algebra\TableReference;
use App\Hierarchy\Storage\Relational\Algebra\Value\AssociativeOperation;
use App\Hierarchy\Storage\Relational\Algebra\Value\BinaryOperation;
use App\Hierarchy\Storage\Relational\Algebra\Value\ColumnReference;
use App\Hierarchy\Storage\Relational\Algebra\Value\ElementOf;
use App\Hierarchy\Storage\Relational\ColumnCoder;
use App\Hierarchy\Storage\Relational\Naming;

class DeletionCommandBuilder
{
    public function __construct(private SchemaDefinition $schemaDef, private Naming $naming, private ColumnCoder $coder)
    {
    }

    /**
     * @param array<int,mixed> $idParams
     */
    public function getCommandForDeleteMultipleNodes(string $keyId, array $idParams): Delete
    {
        $table = new TableReference($this->naming->nodeTableName($keyId));

        $condition = new ElementOf(
            new ColumnReference($table, $this->naming->nodeTablePKName($keyId)),
            array_map(fn ($p) => $this->coder->wrapPrimaryKeyParameter($keyId, $p), $idParams)
        );

        return new Delete(
            $table,
            $condition
        );
    }

    /**
     * @param array<int,mixed> $idParams
     */
    public function getCommandForDeleteMultipleNodesClosure(string $keyId, array $idParams): Delete
    {
        $table = new TableReference($this->naming->closureTableName($keyId));

        $condition = new BinaryOperation(
            new Disjunction(),
            new ElementOf(
                new ColumnReference($table, $this->naming->closureParentColumnName($keyId)),
                array_map(fn ($p) => $this->coder->wrapPrimaryKeyParameter($keyId, $p),
                    $idParams
                )
            ),
            new ElementOf(
                new ColumnReference($table, $this->naming->closureChildColumnName($keyId)),
                array_map(fn ($p) => $this->coder->wrapPrimaryKeyParameter($keyId, $p),
                    $idParams
                )
            )
        );

        return new Delete(
            $table,
            $condition
        );
    }

    /**
     * @param array<int,mixed> $idParams
     */
    public function getSelectForCollectChildByIdReflexive(string $keyId, array $idParams): Select
    {
        $closureTable = new TableReference($this->naming->closureTableName($keyId));
        $nodeTable = new TableReference($this->naming->nodeTableName($keyId));

        $condition = new ElementOf(
            new ColumnReference($closureTable, $this->naming->closureParentColumnName($keyId)),
            array_map(fn ($p) => $this->coder->wrapPrimaryKeyParameter($keyId, $p),
                $idParams
            )
        );

        $projections = [
            new Projection($this->coder->wrapClosureChildColumn($keyId, $closureTable), $this->naming->hierarchyIdColumnName($keyId)),
        ];

        $joins = [
            new Join($nodeTable, new BinaryOperation(
                new Equal(),
                new ColumnReference($closureTable, $this->naming->closureChildColumnName($keyId)),
                new ColumnReference($nodeTable, $this->naming->nodeTablePKName($keyId))
            )),
        ];

        foreach ($this->schemaDef->getKeyFieldIds($keyId) as $fieldId) {
            foreach ($this->schemaDef->getKeyFieldColumns($keyId, $fieldId) as $column) {
                $projections[] = new Projection(
                    $this->coder->wrapColumn($column, $nodeTable), new Identifier($column->getName())
                );
            }
        }

        $orders = [
            new Order(new ColumnReference($closureTable, $this->naming->closureTableDepthName($keyId)), false),
        ];

        return new Select($projections, [$closureTable], $joins, $condition, $orders);
    }

    /**
     * @param array<int,mixed> $idParams
     */
    public function getSelectForCollectSelfById(string $keyId, array $idParams): Select
    {
        $nodeTable = new TableReference($this->naming->nodeTableName($keyId));

        $condition = new ElementOf(
            new ColumnReference($nodeTable, $this->naming->nodeTablePKName($keyId)),
            array_map(fn ($p) => $this->coder->wrapPrimaryKeyParameter($keyId, $p),
                $idParams
            )
        );

        $projections = [
            new Projection($this->coder->wrapPrimaryColumn($keyId, $nodeTable), $this->naming->hierarchyIdColumnName($keyId)),
        ];

        foreach ($this->schemaDef->getKeyFieldIds($keyId) as $fieldId) {
            foreach ($this->schemaDef->getKeyFieldColumns($keyId, $fieldId) as $column) {
                $projections[] = new Projection(
                    $this->coder->wrapColumn($column, $nodeTable), new Identifier($column->getName())
                );
            }
        }

        return new Select($projections, [$nodeTable], [], $condition);
    }

    /**
     * @param array<int,mixed> $scopeParams
     */
    public function getSelectForCollectChildByScopeReflexive(string $keyId, array $scopeParams): Select
    {
        $closureTable = new TableReference($this->naming->closureTableName($keyId));
        $nodeTable = new TableReference($this->naming->nodeTableName($keyId));

        $condition = new ElementOf(
            new ColumnReference($closureTable, $this->naming->nodeOwnScopeColumnName($keyId)),
            array_map(fn ($p) => $this->coder->wrapScopeParameter($keyId, $p),
                $scopeParams
            )
        );

        $projections = [
            new Projection($this->coder->wrapClosureChildColumn($keyId, $closureTable), $this->naming->hierarchyIdColumnName($keyId)),
        ];

        $joins = [
            new Join($nodeTable, new BinaryOperation(
                new Equal(),
                new ColumnReference($closureTable, $this->naming->closureChildColumnName($keyId)),
                new ColumnReference($nodeTable, $this->naming->nodeTablePKName($keyId))
            )),
        ];

        foreach ($this->schemaDef->getKeyFieldIds($keyId) as $fieldId) {
            foreach ($this->schemaDef->getKeyFieldColumns($keyId, $fieldId) as $column) {
                $projections[] = new Projection(
                    $this->coder->wrapColumn($column, $nodeTable), new Identifier($column->getName())
                );
            }
        }

        $orders = [
            new Order(new ColumnReference($closureTable, $this->naming->closureTableDepthName($keyId)), false),
        ];

        return new Select($projections, [$closureTable], $joins, $condition, $orders);
    }

    /**
     * @param array<int,mixed> $scopeParams
     */
    public function getSelectForCollectChildByScope(string $keyId, array $scopeParams): Select
    {
        $nodeTable = new TableReference($this->naming->nodeTableName($keyId));

        $condition = new ElementOf(
            new ColumnReference($nodeTable, $this->naming->nodeOwnScopeColumnName($keyId)),
            array_map(fn ($p) => $this->coder->wrapScopeParameter($keyId, $p),
                $scopeParams
            )
        );

        $projections = [
            new Projection($this->coder->wrapPrimaryColumn($keyId, $nodeTable), $this->naming->hierarchyIdColumnName($keyId)),
        ];

        foreach ($this->schemaDef->getKeyFieldIds($keyId) as $fieldId) {
            foreach ($this->schemaDef->getKeyFieldColumns($keyId, $fieldId) as $column) {
                $projections[] = new Projection(
                    $this->coder->wrapColumn($column, $nodeTable), new Identifier($column->getName())
                );
            }
        }

        return new Select($projections, [$nodeTable], [], $condition);
    }

    /**
     * @param array<int,mixed> $idParams
     */
    public function getSelectForReferencedNodes(string $keyId, array $columns, array $idParams): Select
    {
        $nodeTable = new TableReference($this->naming->nodeTableName($keyId));

        $conditions = [];

        // TOOODOOOO
        foreach ($columns as $col) {
            $conditions[] = new ElementOf(
                new ColumnReference($nodeTable, $this->naming->fieldColumnToName($col)),
                array_map(fn ($p) => $this->coder->wrapColumnParameter($col, $p), $idParams)
            );
        }

        $projections = [
            new Projection($this->coder->wrapPrimaryColumn($keyId, $nodeTable), $this->naming->hierarchyIdColumnName($keyId)),
        ];

        foreach ($this->schemaDef->getKeyFieldIds($keyId) as $fieldId) {
            foreach ($this->schemaDef->getKeyFieldColumns($keyId, $fieldId) as $column) {
                $projections[] = new Projection(
                    $this->coder->wrapColumn($column, $nodeTable), new Identifier($column->getName())
                );
            }
        }

        return new Select($projections, [$nodeTable], [], new AssociativeOperation(new Disjunction(), $conditions));
    }
}
