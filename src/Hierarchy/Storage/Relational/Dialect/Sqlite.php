<?php

namespace App\Hierarchy\Storage\Relational\Dialect;

use App\Hierarchy\Storage\Relational\Algebra\ForeignKey;
use App\Hierarchy\Storage\Relational\Algebra\Identifier;
use App\Hierarchy\Storage\Relational\Algebra\Operator;
use App\Hierarchy\Storage\Relational\Algebra\Operator\FunctionInterface;
use App\Hierarchy\Storage\Relational\Algebra\TableColumn;
use App\Hierarchy\Storage\Relational\Algebra\Value;
use App\Hierarchy\Storage\Relational\Algebra\Value\ValueInterface;

class Sqlite extends SqlBase implements DialectInterface
{
    public function stringQueryViewNames(): string
    {
        return "SELECT name FROM sqlite_schema WHERE type ='view'";
    }

    public function stringQueryTableNames(): string
    {
        return "SELECT name FROM sqlite_schema WHERE type ='table' AND name NOT LIKE 'sqlite_%'";
    }

    protected function foreignKeyToString(ForeignKey $fk): string
    {
        return parent::foreignKeyToString($fk).' DEFERRABLE INITIALLY DEFERRED';
    }

    protected function escapeIdentifier(Identifier $identifier): string
    {
        return sprintf('"%s"', str_replace(['/', '"'], '', $identifier->getString()));
    }

    protected function tablePrimaryColumnToString(TableColumn $column): string
    {
        return sprintf(
            'PRIMARY KEY(%s%s)',
            $this->escapeIdentifier($column->getName()),
            $column->isSerial() ? ' AUTOINCREMENT' : ''
        );
    }

    protected function operatorSymbol($operator): string
    {
        switch (get_class($operator)) {
            case Operator\Comparison\Equal::class:
                if ($operator->allowNull()) {
                    return 'IS';
                }

                return '=';

            case Operator\Comparison\NotEqual::class:
                if ($operator->allowNull()) {
                    return 'IS NOT';
                }

                return '<>';

            case Operator\String\Concat::class:
                return '||';
            default:
                return parent::operatorSymbol($operator);
        }
    }

    protected function valueToString(ValueInterface $v): string
    {
        switch (get_class($v)) {
            case Value\DefaultValue::class:
                return 'NULL';
        }

        return parent::valueToString($v);
    }

    public function stringSwitchForeignKey(bool $on): ?string
    {
        return sprintf('PRAGMA foreign_keys = %s;', $on ? 'On' : 'Off');
    }

    protected function dataTypeToString($type): string
    {
        if ('SERIAL' === $type) {
            return 'INTEGER';
        }

        return $type;
    }

    protected function functionToString(FunctionInterface $function): string
    {
        switch (get_class($function)) {
            case Operator\Function\Unhex::class:
                return '';
            case Operator\Function\Hex::class:
                return '';
            default:
                return parent::functionToString($function);
        }
    }
}
