<?php

namespace App\Hierarchy\Storage\Relational\Dialect;

use App\Hierarchy\Storage\Relational\Algebra\CreateTable;
use App\Hierarchy\Storage\Relational\Algebra\Identifier;
use App\Hierarchy\Storage\Relational\Algebra\Operator;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Comparison;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Logic;
use App\Hierarchy\Storage\Relational\Algebra\TableColumn;
use App\Hierarchy\Storage\Relational\Algebra\Update;
use App\Hierarchy\Storage\Relational\Algebra\Value;

class MySql extends SqlBase implements DialectInterface
{
    public function stringQueryViewNames(): string
    {
        return "SHOW FULL TABLES WHERE Table_Type = 'VIEW'";
    }

    public function stringQueryTableNames(): string
    {
        return "SHOW FULL TABLES WHERE Table_Type = 'BASE TABLE'";
    }

    protected function tableColumnToString(TableColumn $column): string
    {
        $result = $this->escapeIdentifier($column->getName());
        $result .= ' '.$this->dataTypeToString($column->getType());

        if (!$column->isNullable()) {
            $result .= ' NOT NULL';
        }

        if ($column->isSerial()) {
            $result .= ' AUTO_INCREMENT';
        } elseif ($column->hasDefault()) {
            $result .= ' DEFAULT '.$this->constantToString($column->getDefault());
        }

        return $result;
    }

    protected function dataTypeToString($type): string
    {
        if ('SERIAL' === $type) {
            return 'INTEGER UNSIGNED';
        }

        return $type;
    }

    protected function escapeIdentifier(Identifier $identifier): string
    {
        return sprintf('`%s`', str_replace(['/', '`'], '', $identifier->getString()));
    }

    public function updateToString(Update $update): string
    {
        $query = 'UPDATE '.$this->tableReferenceToString($update->getTable()).PHP_EOL;

        if ($update->getSelect()) {
            $select = $update->getSelect();
            $tables = $select->getTables();
            if (1 !== count($tables)) {
                throw new \Exception('update with multiple joins not supported');
            }
            $query .= 'INNER JOIN (';
            $this->indent();
            $query .= $this->i().$this->selectToString($update->getSelect());
            $this->outdent();
            $query .= ') _alias_'.md5($query).' '.PHP_EOL;
        }

        $query .= 'SET ';
        $this->indent();
        foreach ($update->getSetters() as $i => $s) {
            $query .= ($i ? ',' : '').PHP_EOL.$this->i().$this->setterToString($s);
        }
        $this->outdent();

        if ($update->getCondition()) {
            $query .= $this->i().' WHERE'.PHP_EOL;
            $this->indent();
            $query .= $this->i().$this->valueToString($update->getCondition());
            $this->outdent();
        }

        return $query;
    }

    protected function associativeOperationToString(Value\AssociativeOperation $associativeOperation): string
    {
        if ($associativeOperation->getOperator() instanceof Operator\String\Concat) {
            return 'CONCAT('.
                implode(', ', array_map(fn ($v) => $this->valueToString($v), $associativeOperation->getOperands()))
             .')';
        }

        return parent::associativeOperationToString($associativeOperation);
    }

    protected function binaryOperationToString(Value\BinaryOperation $binaryOperation): string
    {
        if ($binaryOperation->getOperator() instanceof Operator\String\Concat) {
            return 'CONCAT('.
            $this->valueToString($binaryOperation->getLeftOperand()).', '.
            $this->valueToString($binaryOperation->getRightOperand()).
            ')';
        } elseif ($binaryOperation->getOperator() instanceof Comparison\NotEqual) {
            return $this->unaryOperationToString(
                new Value\UnaryOperation(new Logic\Negation(), new Value\BinaryOperation(
                    new Comparison\Equal(true),
                    $binaryOperation->getLeftOperand(),
                    $binaryOperation->getRightOperand()
                ))
            );
        }

        return parent::binaryOperationToString($binaryOperation);
    }

    public function stringSwitchForeignKey($on): ?string
    {
        return sprintf('SET foreign_key_checks = %d;', $on ? 1 : 0);
    }

    public function addForeignKeysTableToString(CreateTable $createTable): void
    {
    }
}
