<?php

namespace App\Hierarchy\Storage\Relational\Service;

use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Storage\Relational\Algebra\Aggregation\Maximum;
use App\Hierarchy\Storage\Relational\Algebra\Identifier;
use App\Hierarchy\Storage\Relational\Algebra\Insert;
use App\Hierarchy\Storage\Relational\Algebra\Join;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Comparison\Equal;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Function\Coalesce;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Function\Unhex;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Logic\Conjunction;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Logic\Disjunction;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Numeric\Addition;
use App\Hierarchy\Storage\Relational\Algebra\Projection;
use App\Hierarchy\Storage\Relational\Algebra\Select;
use App\Hierarchy\Storage\Relational\Algebra\TableReference;
use App\Hierarchy\Storage\Relational\Algebra\Value\Aggregation;
use App\Hierarchy\Storage\Relational\Algebra\Value\AssociativeOperation;
use App\Hierarchy\Storage\Relational\Algebra\Value\BinaryOperation;
use App\Hierarchy\Storage\Relational\Algebra\Value\ColumnReference;
use App\Hierarchy\Storage\Relational\Algebra\Value\Constant;
use App\Hierarchy\Storage\Relational\Algebra\Value\DefaultValue;
use App\Hierarchy\Storage\Relational\Algebra\Value\FunctionApplication;
use App\Hierarchy\Storage\Relational\Algebra\Value\Parameter;
use App\Hierarchy\Storage\Relational\Algebra\Value\Selection;
use App\Hierarchy\Storage\Relational\Algebra\Value\Tuple;
use App\Hierarchy\Storage\Relational\ColumnCoder;
use App\Hierarchy\Storage\Relational\Naming;

class CreationCommandBuilder
{
    public function __construct(private SchemaDefinition $schemaDef, private Naming $naming, private ColumnCoder $coder)
    {
    }
    /**
     * @param mixed $keyId
     */
    public function getSelectForScopeParentCheck($keyId, Parameter $scopeParam, Parameter $parentParam): Select
    {
        $table = new TableReference($this->naming->nodeTableName($keyId));

        return new Select(
            [new Projection(new Constant(1))],
            [$table], [],
            new BinaryOperation(
                new Equal(),
                new Tuple([
                    new ColumnReference($table, $this->naming->nodeOwnScopeColumnName($keyId)),
                    new ColumnReference($table, $this->naming->nodeTablePKName($keyId)),
                ]),
                new Tuple([
                    $this->coder->wrapScopeParameter($keyId, $scopeParam),
                    $this->coder->wrapParentParameter($keyId, $parentParam),
                ])
            )
        );
    }
    /**
     * @param mixed $fieldsToCheck
     */
    public function getSelectForUniquenessCheckNew(string $keyId, Parameter $scopeParam, Parameter $parentParam, $fieldsToCheck) : Select
    {
        $table = new TableReference($this->naming->nodeTableName($keyId));
        $tableH = new TableReference($this->naming->hierarchyViewName($keyId));
        $conditions = [];
        $checks = [new Constant(0)];
        $projections = [];

        foreach ($fieldsToCheck as $fieldId => $params) {
            $columns = [];
            foreach ($this->schemaDef->getKeyFieldColumns($keyId, $fieldId) as $i => $column) {
                $columns[] = new ColumnReference($table, $this->naming->fieldColumnToName($column));
            }

            $checks[$fieldId] = new BinaryOperation(
                new Equal(),
                new Tuple($columns),
                new Tuple($params)
            );
        }

        $conditions[] = new AssociativeOperation(new Disjunction(), array_values($checks));

        if ($this->schemaDef->isKeyScoped($keyId)) {
            $conditions[] = new BinaryOperation(new Equal(),
                $this->coder->wrapScopeParameter($keyId, $scopeParam),
                new ColumnReference($tableH, $this->naming->hierarchyScopeColumnName($keyId))
            );
        }

        if ($this->schemaDef->isKeyReflexive($keyId)) {
            $conditions[] = new BinaryOperation(new Equal(),
                $this->coder->wrapParentParameter($keyId, $parentParam),
                new ColumnReference($tableH, $this->naming->hierarchyParentColumnName($keyId))
            );
        }

        foreach ($checks as $fieldId => $check) {
            $projections[] = new Projection(new Aggregation(new Maximum(), $check), new Identifier($fieldId));
        }

        return new Select(
            $projections,
            [$table], [new Join($tableH, new BinaryOperation(
                new Equal(),
                new ColumnReference($tableH, $this->naming->hierarchyIdColumnName($keyId)),
                new ColumnReference($table, $this->naming->nodeTablePKName($keyId))
            ))],
            new AssociativeOperation(new Conjunction(), $conditions)
        );
    }

    public function getCommandForCreateNode(string $keyId, Parameter $idParam, Parameter $scopeParam, Parameter $parentParam): Insert
    {
        $tableName = $this->naming->nodeTableName($keyId);

        $columns = [];
        $values = [];

        if ($this->schemaDef->isKeyScoped($keyId)) {
            $columns[] = $this->naming->nodeOwnScopeColumnName($keyId);
            $values[] = $this->coder->wrapScopeParameter($keyId, $scopeParam);
        }
        if ($this->schemaDef->isKeyOrdered($keyId)) {
            $orderView = new TableReference($this->naming->normalizedOrderViewName($keyId));

            $orderConditions = [];

            if ($this->schemaDef->isKeyScoped($keyId)) {
                $orderConditions[] = new BinaryOperation(
                    new Equal(true),
                    new ColumnReference($orderView, $this->naming->normalizedOrderScopeColumnName($keyId)),
                    $this->coder->wrapScopeParameter($keyId, $scopeParam)
                );
            }

            if ($this->schemaDef->isKeyReflexive($keyId)) {
                $orderConditions[] = new BinaryOperation(
                    new Equal(true),
                    new ColumnReference($orderView, $this->naming->normalizedOrderParentColumnName($keyId)),
                    $this->coder->wrapParentParameter($keyId, $parentParam)
                );
            }

            if (empty($orderConditions)) {
                $orderCondition = new Constant(1);
            } else {
                $orderCondition = new AssociativeOperation(new Conjunction(), $orderConditions);
            }

            $columns[] = $this->naming->orderColumnName($keyId);
            $values[] = new BinaryOperation(
                new Addition(),
                new FunctionApplication(
                    new Coalesce(), [
                        new Selection(
                            new Select([
                                new Projection(
                                    new Aggregation(
                                        new Maximum(),
                                        new ColumnReference($orderView, $this->naming->normalizedOrderNormalizedColumnName($keyId))
                                    )
                                )], [$orderView], [], $orderCondition)
                        ),
                        new Constant(0),
                    ]),
                new Constant(1)
            );
        }

        $columns[] = $this->naming->nodeTablePKName($keyId);

        switch ($this->schemaDef->getKeyIdentityColumnType($keyId)) {
            case 'serial':
                $values[] = new DefaultValue();
                break;
            case 'uuid':
                $values[] = new FunctionApplication(new Unhex(), [$idParam]);
                break;
            case 'manual':
                $values[] = $idParam;
                break;
        }

        $isolations = $this->schemaDef->getKeyIsolations($keyId);

        foreach ($isolations as $isolation) {
            $scopeTable = new TableReference($this->naming->scopeTablename($keyId));
            $columns[] = $this->naming->nodeOwnIsolationColumnName($isolation);
            $values[] = new Selection(new Select([
                new Projection(
                    new ColumnReference($scopeTable,
                        $isolation === $this->schemaDef->getKeyScopeId($keyId) ?
                        $this->naming->nodeOwnScopeColumnName($isolation)
                        : $this->naming->nodeOwnIsolationColumnName($isolation)
                    )
                ),
            ], [
                $scopeTable,
            ], [], new BinaryOperation(
                new Equal(),
                new ColumnReference($scopeTable, $this->naming->scopeTablePKName($keyId)),
                $this->coder->wrapScopeParameter($keyId, $scopeParam)
            )));
        }

        foreach ($this->schemaDef->getKeyFieldIds($keyId) as $fieldId) {
            foreach ($this->schemaDef->getKeyFieldColumns($keyId, $fieldId) as $column) {
                $columns[] = $this->naming->fieldColumnToName($column);
                $values[] = $this->coder->wrapColumnParameter($column, new Parameter($column->getName()));
            }
        }

        return new Insert(
            $tableName,
            $columns,
            [$values]
        );
    }

    public function getCommandForClosureInsert(string $keyId, Parameter $scopeParam, Parameter $parentParam, Parameter $childParam, Parameter $depthParam): Insert
    {
        $closureTableName = $this->naming->closureTableName($keyId);
        $missingView = new TableReference($this->naming->closureMissingViewName($keyId));

        $targetColumns = [
            $this->naming->closureParentColumnName($keyId),
            $this->naming->closureChildColumnName($keyId),
            $this->naming->closureTableDepthName($keyId),
        ];

        $sourceColumns = [
            $this->coder->wrapPrimaryKeyParameter($keyId, $parentParam),
            $this->coder->wrapPrimaryKeyParameter($keyId, $childParam),
            $depthParam,
        ];

        if ($this->schemaDef->isKeyScoped($keyId)) {
            $targetColumns[] = $this->naming->nodeOwnScopeColumnName($keyId);
            $sourceColumns[] = $this->coder->wrapScopeParameter($keyId, $scopeParam);
        }

        return new Insert(
            $closureTableName,
            $targetColumns,
            [$sourceColumns]
        );
    }

    public function getCommandForClosureParentInsert(string $keyId, Parameter $scopeParam, Parameter $parentParam, Parameter $childParam): Insert
    {
        $closureTableName = $this->naming->closureTableName($keyId);
        $missingView = new TableReference($this->naming->closureMissingViewName($keyId));

        $closureTable = new TableReference($closureTableName);

        $targetColumns = [
            $this->naming->closureParentColumnName($keyId),
            $this->naming->closureChildColumnName($keyId),
            $this->naming->closureTableDepthName($keyId),
        ];

        $projections = [
            new Projection(new ColumnReference($closureTable, $this->naming->closureParentColumnName($keyId))),
            new Projection(
                $this->coder->wrapPrimaryKeyParameter($keyId, $parentParam)),
            new Projection(
                new BinaryOperation(
                    new Addition(),
                    new ColumnReference($closureTable, $this->naming->closureTableDepthName($keyId)),
                    new Constant(1)
                )
            ),
        ];

        if ($this->schemaDef->isKeyScoped($keyId)) {
            $targetColumns[] = $this->naming->nodeOwnScopeColumnName($keyId);
            $projections[] = new Projection(
                $this->coder->wrapScopeParameter($keyId, $scopeParam));
        }

        $select = new Select($projections, [$closureTable], [], new BinaryOperation(
            new Equal(),
            new ColumnReference($closureTable, $this->naming->closureChildColumnName($keyId)),
            $this->coder->wrapPrimaryKeyParameter($keyId, $childParam)
        ));

        return new Insert(
            $closureTableName,
            $targetColumns,
            $select
        );
    }

    public function getInsertForClosureRepair(string $keyId): Insert
    {
        $closureTableName = $this->naming->closureTableName($keyId);
        $missingView = new TableReference($this->naming->closureMissingViewName($keyId));

        $targetColumns = [
            $this->naming->closureTablePkName($keyId),
            $this->naming->closureParentColumnName($keyId),
            $this->naming->closureChildColumnName($keyId),
            $this->naming->closureTableDepthName($keyId),
        ];

        $sourceColumns = [
            new Projection(new ColumnReference($missingView, $this->naming->closureMissingIdColumn($keyId))),
            new Projection(new ColumnReference($missingView, $this->naming->closureMissingParentColumn($keyId))),
            new Projection(new ColumnReference($missingView, $this->naming->closureMissingChildColumn($keyId))),
            new Projection(new ColumnReference($missingView, $this->naming->closureMissingDepthColumn($keyId))),
        ];

        if ($this->schemaDef->isKeyScoped($keyId)) {
            $targetColumns[] = $this->naming->nodeOwnScopeColumnName($keyId);
            $sourceColumns[] = new Projection(new ColumnReference($missingView, $this->naming->nodeOwnScopeColumnName($keyId)));
        }

        return new Insert(
            $closureTableName,
            $targetColumns,
            new Select($sourceColumns, [$missingView])
        );
    }
}
