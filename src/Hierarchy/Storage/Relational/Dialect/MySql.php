<?php

namespace App\Hierarchy\Storage\Relational\Dialect;

use App\Hierarchy\Storage\Relational\Algebra;
use App\Hierarchy\Storage\Relational\Algebra\Identifier;
use App\Hierarchy\Storage\Relational\Algebra\Select;
use App\Hierarchy\Storage\Relational\Algebra\Insert;
use App\Hierarchy\Storage\Relational\Algebra\Update;
use App\Hierarchy\Storage\Relational\Algebra\Setter;
use App\Hierarchy\Storage\Relational\Algebra\Delete;
use App\Hierarchy\Storage\Relational\Algebra\CreateView;
use App\Hierarchy\Storage\Relational\Algebra\CreateTable;
use App\Hierarchy\Storage\Relational\Algebra\TableColumn;
use App\Hierarchy\Storage\Relational\Algebra\ForeignKey;
use App\Hierarchy\Storage\Relational\Algebra\Projection;
use App\Hierarchy\Storage\Relational\Algebra\Join;
use App\Hierarchy\Storage\Relational\Algebra\Order;
use App\Hierarchy\Storage\Relational\Algebra\TableReference;
use App\Hierarchy\Storage\Relational\Algebra\Value\ValueInterface;
use App\Hierarchy\Storage\Relational\Algebra\Operator\FunctionInterface;
use App\Hierarchy\Storage\Relational\Algebra\Value;
use App\Hierarchy\Storage\Relational\Algebra\Aggregation;
use App\Hierarchy\Storage\Relational\Algebra\Operator;
use App\Hierarchy\Storage\Relational\Algebra\Windowing;
use App\Hierarchy\Storage\Relational\Algebra\Windowing\WindowingInterface;

class MySql extends SqlBase implements DialectInterface {
	

	protected function tableColumnToString(TableColumn $column, bool $serial) {
		$result = $this->escapeIdentifier($column->getName());
		$result .= ' ' . $this->dataTypeToString($column->getType());

		if(!$column->isNullable()) {
			$result .= ' NOT NULL';
		}

		if($serial) {
			$result .= ' AUTO_INCREMENT';
		} elseif($column->hasDefault()) {
			$result .= ' DEFAULT ' . $this->constantToString($column->getDefault());
		}

		return $result;
	}


	protected function escapeIdentifier(Identifier $identifier) {
		return sprintf('`%s`', str_replace(['/','`'], '', $identifier->getString()));
	}

	public function updateToString(Update $update) {
		$query = 'UPDATE ' . $this->tableReferenceToString($update->getTable()) . PHP_EOL;



		if($update->getSelect()) {
			$select = $update->getSelect();
			$tables = $select->getTables();
			if(count($tables) !== 1) {
				throw new Exception('update with multiple joins not supported');
			}
			$query .= 'INNER JOIN (';
			$this->indent();
			$query .= $this->i() . $this->selectToString($update->getSelect());
			$this->outdent();
			$query .= ') ' . md5($query) . ' ' . PHP_EOL;
		}

		$query .= 'SET ';
		$this->indent();
		foreach ($update->getSetters() as $i => $s) {
			$query .= ($i?',':'') . PHP_EOL . $this->i() . $this->setterToString($s);
		}
		$this->outdent();
		
		if($update->getCondition()) {
			$query .= $this->i() . ' WHERE' . PHP_EOL;
			$this->indent();
			$query .= $this->i() . $this->valueToString($update->getCondition());
			$this->outdent();
		}

		return $query;
	
	}

	protected function associativeOperationToString(Value\AssociativeOperation $associativeOperation) {
		if($associativeOperation->getOperator() instanceof Operator\String\Concat) {
			return 'CONCAT(' . 
				implode(', ', array_map(fn($v) => $this->valueToString($v), $associativeOperation->getOperands()))
			 . ')';
		}

		return parent::associativeOperationToString($associativeOperation);
	}

	protected function binaryOperationToString(Value\BinaryOperation $binaryOperation) {
		if($binaryOperation->getOperator() instanceof Operator\String\Concat) {
			return 'CONCAT(' . 
			$this->valueToString($binaryOperation->getLeftOperand()) . ', ' . 
			$this->valueToString($binaryOperation->getRightOperand()) .
			')';
		}
		
		return parent::binaryOperationToString($binaryOperation);
	}

	public function stringSwitchForeignKey($on) {
		return sprintf('SET foreign_key_checks = %d;', $on ? 1 : 1);
	}
}