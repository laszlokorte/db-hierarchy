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

abstract class SqlBase implements DialectInterface {
	private const INDENT = "\t";
	private $depth = 0;

	protected function indent($delta = 1) {
		$this->depth += $delta;
	}
	protected function outdent($delta = 1) {
		$this->depth -= $delta;
	}

	protected function i($extra = 0) {
		return str_repeat(self::INDENT, $this->depth + $extra);
	}

	public function stringSwitchForeignKey($on) {
		return false;
	}

	public function selectToString(Select $select) {
		return $this->selectToStringInternal($select, true);
	}

	protected function selectToStringInternal(Select $select, bool $allowOrder = true) {
		$query = $this->i() . "SELECT ";
		$this->indent();
		foreach($select->getProjections() AS $i => $p) {
			$query .= ($i?',':'') . PHP_EOL . $this->i() . $this->projectionToString($p);
		}
		$this->outdent();
		$query .= PHP_EOL;
		if(!empty($select->getTables())) {
			$query .= $this->i() .  'FROM';
			$this->indent();
			foreach($select->getTables() AS $i => $t) {
				$query .= ($i?',':'') . PHP_EOL . $this->i() . $this->tableReferenceToString($t);
			}
			$this->outdent();
			$query .= PHP_EOL;
		}
		foreach($select->getJoins() AS $j) {
			$query .= $this->i() . $this->joinToString($j) . PHP_EOL;
		}
		
		if($select->getCondition()) {
			$query .= $this->i() . 'WHERE' . PHP_EOL;
			$this->indent();
			$query .= $this->i() . $this->valueToString($select->getCondition());
			$this->outdent();
		}

		if($select->getGroupings()) {
			$query .= $this->i() . 'GROUP BY' . PHP_EOL;
			$this->indent();
			foreach($select->getGroupings() AS $i => $g) {
				$query .= ($i?',':'') . PHP_EOL . $this->i() . $this->orderToString(g);
			}
			$this->outdent();
		}

		if($select->getHaving()) {
			$query .= $this->i() . 'HAVING' . PHP_EOL;
			$this->indent();
			$query .= $this->i() . $this->valueToString($select->getHaving());
			$this->outdent();
		}

		if($select->getUnions()) {
			foreach ($select->getUnions() as $u) {
				$query .= PHP_EOL . $this->i() . 'UNION' . PHP_EOL;
				$query .= $this->selectToStringInternal($u, false);
			}
		}

		if(!empty($select->getOrders())) {
			if(!$allowOrder) {
				throw new \Exception("union queries must not ordered");
			}
			$query .= PHP_EOL . $this->i() . 'ORDER BY';
			$this->indent();
			foreach($select->getOrders() AS $i => $o) {
				$query .= ($i?',':'') . PHP_EOL . $this->i() . $this->orderToString($o);
			}
			$this->outdent();
			$query .= PHP_EOL;
		}
		
		return $query;
	}

	protected function projectionToString(Projection $p) {
		$valueString = $this->valueToString($p->getValue());

		if($p->getAlias()) {
			return sprintf('%s AS %s', $valueString, $this->escapeIdentifier($p->getAlias()));
		} else {
			return $valueString;
		}
	}

	protected function tableReferenceToString(TableReference $t) {
		$tableName = $this->escapeIdentifier($t->getName());

		if($t->getAlias()) {
			return sprintf('%s %s', $tableName, $this->escapeIdentifier($t->getAlias()));
		} else {
			return $tableName;
		}
	}

	protected function joinToString(Join $j) {
		return $this->joinDirectionToString($j->getDirection()) . ' JOIN ' . $this->tableReferenceToString($j->getTable()) . PHP_EOL .
			$this->i() . 'ON ' .  $this->valueToString($j->getCondition());
	}

	protected function joinDirectionToString($dir) {
		switch($dir) {
			case 'INNER':
			case 'LEFT':
			case 'RIGHT':
			case 'OUTER':
				return $dir;
			default:
				throw new \Exception("unknown join direction:" . $dir);
		}
	}

	protected function valueToString(ValueInterface $v) {
		switch(get_class($v)) {
			case TableReference::class:
				return sprintf('%s.*', $this->escapeIdentifier($v->getUsageName()));
			case Value\DefaultValue::class:
				return 'DEFAULT';
			case Value\Aggregation::class:
				return $this->aggregationToString($v);
			case Value\AssociativeOperation::class:
				return $this->associativeOperationToString($v);
			case Value\BinaryOperation::class:
				return $this->binaryOperationToString($v);
			case Value\ColumnReference::class:
				return $this->columnReferenceToString($v);
			case Value\Constant::class:
				return $this->constantToString($v);
			case Value\Existence::class:
				return $this->existenceToString($v);
			case Value\ElementOf::class:
				return $this->elementOfToString($v);
			case Value\FunctionApplication::class:
				return $this->functionApplicationToString($v);
			case Value\Parameter::class:
				return $this->parameterToString($v);
			case Value\Projected::class:
				return $this->projectedToString($v);
			case Value\Tuple::class:
				return $this->tupleToString($v);
			case Value\UnaryOperation::class:
				return $this->unaryOperationToString($v);
			case Value\Windowing::class:
				return $this->windowingToString($v);
			case Value\Cases::class:
				return $this->casesToString($v);
			case Value\Selection::class:
				return '(' . $this->selectToString($v->getSelect()) . ')';
			default:
				throw new \Exception("unknown value:" . get_class($v));
		}
	}

	protected function aggregationToString(Value\Aggregation $aggregation) {
		return sprintf('%s(%s)', $this->aggregationName($aggregation->getAggregation()), $this->valueToString($aggregation->getValue()));
	}

	protected function aggregationName($aggregation) {
		switch(get_class($aggregation)) {
			case Aggregation\Average::class:
				return 'AVG';
			case Aggregation\Count::class:
				return 'COUNT';
			case Aggregation\Maximum::class:
				return 'MAX';
			case Aggregation\Minimum::class:
				return 'MIN';
			case Aggregation\Sum::class:
				return 'SUM';
			default: 
				throw new \Exception("unknown aggregation:" . get_class($a));
		}
	}

	protected function associativeOperationToString(Value\AssociativeOperation $associativeOperation) {
		return implode(' ' . $this->operatorSymbol($associativeOperation->getOperator()) . PHP_EOL . $this->i(), array_map(fn($v) => $this->valueToString($v), $associativeOperation->getOperands()));
	}

	protected function operatorSymbol($operator) {
		switch(get_class($operator)) {
			case Operator\Comparison\Equal::class:
				if($operator->allowNull()) {
					return '<=>';
				} else {
					return '=';
				}
			case Operator\Comparison\GreaterThan::class:
				return '>';
			case Operator\Comparison\GreaterThanEqual::class:
				return '>=';
			case Operator\Comparison\LessThan::class:
				return '<';
			case Operator\Comparison\LessThanEqual::class:
				return '<=';
			case Operator\Comparison\NotEqual::class:
				if($operator->allowNull()) {
					throw new \Exception('nullsafe not-equals not supported, please rewrite to explicit negation');
				} else {
					return '<>';
				}
			case Operator\Logic\Conjunction::class:
				return 'AND';
			case Operator\Logic\Disjunction::class:
				return 'OR';
			case Operator\Logic\Negation::class:
				return 'NOT';
			case Operator\Numeric\Addition::class:
				return '+';
			case Operator\Numeric\Subtraction::class:
				return '-';
			case Operator\Numeric\Multiplication::class:
				return '*';
			case Operator\Numeric\Division::class:
				return '/';
			case Operator\String\Concat::class:
				throw new \Exception('concat operator not supported, please rewrite to CONCAT() function');
			default: 
				throw new \Exception("unknown operator" . get_class($operator));
		}
	}

	protected function binaryOperationToString(Value\BinaryOperation $binaryOperation) {
		return sprintf('(%s %s %s)', 
			$this->valueToString($binaryOperation->getLeftOperand()),
			$this->operatorSymbol($binaryOperation->getOperator()),
			$this->valueToString($binaryOperation->getRightOperand())
		);
	}

	protected function columnReferenceToString(Value\ColumnReference $columnReference) {
		return sprintf('%s.%s', 
			$this->escapeIdentifier($columnReference->getTable()->getUsageName()), 
			$this->escapeIdentifier($columnReference->getName())
		);
	}

	protected function constantToString(Value\Constant $constant) {
		return $this->escapeLiteral($constant->getValue());
	}

	protected function existenceToString(Value\Existence $existence) {
		$this->indent();
		$sub = $this->selectToString($existence->getSelect());
		$this->outdent();
		return 'EXISTS ('. PHP_EOL. $sub . PHP_EOL . $this->i() .')';
	}

	protected function elementOfToString(Value\ElementOf $elementOf) {
		$this->indent();
		if($elementOf->getSelect() instanceof Select) {
			$sub = $this->selectToString($elementOf->getSelect());
		} else {
			$sub = implode(', ', array_map(fn($v) => $this->valueToString($v), $elementOf->getSelect()));
		}

		$this->outdent();
		return '(' . $this->valueToString($elementOf->getValue()) . ') IN ('. PHP_EOL. $sub . PHP_EOL . $this->i() .')';
	}

	protected function functionApplicationToString(Value\FunctionApplication $functionApplication) {
		$function = $functionApplication->getFunction();
		$args = $functionApplication->getArguments();

		return $this->functionToString($function) . '(' . 
			implode(', ', array_map(
				fn($a) => $this->valueToString($a),
				$args
			)) .
		')';
	}

	protected function functionToString(FunctionInterface $function) {
		switch(get_class($function)) {
			case Operator\Function\Coalesce::class:
				return 'COALESCE';
			case Operator\Function\IfElse::class:
				return 'IF';
			case Operator\Function\NullIf::class:
				return 'NULLIF';
			case Operator\Function\NamedFunction::class:
				return $function->getName();
			default:
				throw new \Exception("unknown function " . get_class($function));
		}
	}

	public function parameterToString(Value\Parameter $parameter) {
		return ':' . substr(md5($parameter->getName()), 0, 5) . preg_replace('/[^a-z]/i', '', $parameter->getName());
	}

	protected function projectedToString(Value\Projected $projected) {
		$projection = $projected->getProjection();

		if($projection->getAlias()) {
			return $this->escapeIdentifier($projection->getAlias());
		} else {
			return $this->valueToString($projection->getValue());
		}
	}

	protected function tupleToString(Value\Tuple $tuple) {
		return sprintf('(%s)', 
			implode(', ', array_map(fn($v) => $this->valueToString($v), $tuple->getValues()))
		);
	}

	protected function unaryOperationToString(Value\UnaryOperation $unaryOperation) {
		return sprintf('(%s %s)', 
			$this->operatorSymbol($unaryOperation->getOperator()),
			$this->valueToString($unaryOperation->getOperand())
		);
	}

	protected function casesToString(Value\Cases $cases) {
		if($cases->count() > 0) {
			$result = $this->i() . 'CASE';
			$this->indent();
			for ($i=0; $i < $cases->count(); $i++) { 
				$result .= PHP_EOL . $this->i() . 'WHEN ';
				$result .= PHP_EOL . $this->i() . $this->valueToString($cases->getCondition($i));
				$result .= PHP_EOL . $this->i() . 'THEN ';
				$result .= PHP_EOL . $this->i() . $this->valueToString($cases->getConsequence($i));
			}

			if($cases->getFallback()) {
				$result .= PHP_EOL . $this->i() . 'ELSE ' . $this->valueToString($cases->getFallback());
			}
			$this->outdent();
			$result .= PHP_EOL . $this->i() . 'END';
			return $result;
		} else {
			return $this->valueToString($cases->getFallback());
		}
	}

	protected function windowingToString(Value\Windowing $windowing) {
		$function = $windowing->getWindowFunction();
		$partitions = $windowing->getPartionValues();
		$orders = $windowing->getOrders();

		$result = $this->windowFunctionToString($function);
		$result .= ' OVER(';
		if(!empty($partitions)) {
			$result .= PHP_EOL . $this->i(1) . 'PARTITION BY';
			foreach ($partitions as $i => $p) {
				$result .= ($i?', ':'')  . $this->valueToString($p);
			}
			$result .= PHP_EOL;
		}
		if(!empty($orders)) {
			$result .= $this->i(1) .'ORDER BY ';
			foreach ($orders as $i => $o) {
				$result .= ($i?', ':'') . $this->orderToString($o);
			}
			$result .= PHP_EOL;
		}
		$result .=  $this->i(0) .  ')';

		return $result;
	}

	protected function windowFunctionToString(WindowingInterface $windowing) {
		switch(get_class($windowing)) {
			case Windowing\AggregationWindow::class:
				$v = $this->valueToString($windowing->getValue());
				$a = $windowing->getAggregation();
				switch(get_class($a)) {
					case  Windowing\Aggregation\Average::class:
						return 'AVG('.$v.')';
					case  Windowing\Aggregation\Count::class:
						return 'COUNT('.$v.')';
					case  Windowing\Aggregation\Maximum::class:
						return 'MAX('.$v.')';
					case  Windowing\Aggregation\Minimum::class:
						return 'MIN('.$v.')';
					case  Windowing\Aggregation\Sum::class:
						return 'SUM('.$v.')';
					default: 
						throw new \Exception("unknown aggregating window function " . get_class($a));
				}
			case Windowing\RankWindow::class:
				$r = $windowing->getRank();
				switch(get_class($r)) {
					case Windowing\Rank\CumulativeDistance::class:
						return 'CUME()';
					case Windowing\Rank\DenseRank::class:
						return 'DENSE_RANK()';
					case Windowing\Rank\NTile::class:
						return 'NTILE('.$r->getBuckets().')';
					case Windowing\Rank\PercentRank::class:
						return 'PERCENT_RANK()';
					case Windowing\Rank\Rank::class:
						return 'RANK()';
					case Windowing\Rank\RowNumber::class:
						return 'ROW_NUMBER()';
					default: 
						throw new \Exception("unknown rank window function " . get_class($r));
				}
			case Windowing\ValueWindow::class:
				$v = $this->valueToString($windowing->getValue());
				$f = $windowing->getFunction();
				switch(get_class($f)) {
					case Windowing\Value\FirstValue::class:
						return 'FIRST_VALUE('.$v.')';
					case Windowing\Value\lastValue::class:
						return 'LAST_VALUE('.$v.')';
					case Windowing\Value\Lag::class:
						return 'LAG(' . $v 
						. (($f->getOffset() != 1 || $f->getDefault() !== null) ? ', ' . $f->getOffset():'') 
						. ($f->getDefault() !== null ? ', ' . $this->valueToString($f->getDefault()):'') 
						. ')';
					case Windowing\Value\Lead::class:
						return 'LEAD(' . $v 
						. (($f->getOffset() != 1 || $f->getDefault() !== null) ? ', ' . $f->getOffset():'') 
						. ($f->getDefault() !== null ? ', ' . $this->valueToString($f->getDefault()):'') 
						. ')';
					default: 
						throw new \Exception("unknown value window function " . get_class($f));
				}

			default: 
				throw new \Exception("unknown window function type " . get_class($windowing));
		}
	}

	protected function orderToString(Order $order) {
		return sprintf('%s %s', $this->valueToString($order->getValue()), $order->isAscending() ? 'ASC' : 'DESC');
	}


	public function insertToString(Insert $insert) {
		$query = 'INSERT INTO ' . $this->escapeIdentifier($insert->getTable()) . PHP_EOL;
		if(!empty($insert->getColumns())) {
			$query .= '('. implode(', ', array_map(
				fn($name) => $this->escapeIdentifier($name),
				$insert->getColumns()
			)) .')' . PHP_EOL;
		}

		if($insert->getRows() instanceof Select) {
			$query .= $this->selectToString($insert->getRows());
		} else {
			$query .= 'VALUES';
			foreach ($insert->getRows() as $i => $row) {
				$query .= ($i?',':'') . PHP_EOL;
				if(empty($row)) {
					$query .= 'DEFAULT VALUES';
					continue;
				}
				$query .= '('. implode(', ', array_map(
				fn($c) => $this->valueToString($c),
				$row
			)) .')' . PHP_EOL;
			}
		}

		return $query;
	}

	public function updateToString(Update $update) {
		$query = 'UPDATE ' . $this->tableReferenceToString($update->getTable()) . PHP_EOL;
		$query .= 'SET ';
		$this->indent();
		foreach ($update->getSetters() as $i => $s) {
			$query .= ($i?',':'') . PHP_EOL . $this->i() . $this->setterToString($s);
		}
		$this->outdent();

		if($update->getSelect()) {
			$query .= $this->i() . ' FROM (' . PHP_EOL;
			$this->indent();
			$query .= $this->i() . $this->selectToString($update->getSelect());
			$this->outdent();
			$query .= $this->i() . ')' . PHP_EOL;
		}
		
		if($update->getCondition()) {
			$query .= $this->i() . ' WHERE' . PHP_EOL;
			$this->indent();
			$query .= $this->i() . $this->valueToString($update->getCondition());
			$this->outdent();
		}

		return $query;
	}

	protected function setterToString(Setter $setter) {
		return sprintf('%s = %s', $this->escapeIdentifier($setter->getColumn()->getName()), $this->valueToString($setter->getValue()));
	}

	public function deleteToString(Delete $delete) {
		$query = 'DELETE FROM ' . $this->tableReferenceToString($delete->getTable()) . PHP_EOL;

		
		if($delete->getCondition()) {
			$query .= $this->i() . 'WHERE' . PHP_EOL;
			$this->indent();
			$query .= $this->i() . $this->valueToString($delete->getCondition());
			$this->outdent();
		}

		return $query;
	}

	public function createViewToString(CreateView $createView) {
		return "CREATE VIEW IF NOT EXISTS " . $this->escapeIdentifier($createView->getName()) .
		" AS " . PHP_EOL . $this->selectToString($createView->getQuery()) . ";";
	}

	public function createTableToString(CreateTable $createTable) {
		$indent = "\t";
		$query = "CREATE TABLE IF NOT EXISTS ";
		$query .= $this->escapeIdentifier($createTable->getName()) . "(". PHP_EOL;
			$this->indent();

			foreach($createTable->getColumns() AS $column) {
				$query .= $this->i() . $this->tableColumnToString(
					$column,
					$column->getName() == $createTable->getPrimaryKey()
				) . ',' . PHP_EOL;
			}
			$query .= $this->i() . $this->tablePrimaryColumnToString($createTable->getPrimaryKey(), true);
			foreach($createTable->getUniques() AS $unique) {
				$query .= ',' . PHP_EOL . $this->i() . $this->uniqueIndexToString($unique);
			}
			foreach($createTable->getForeignKeys() AS $fk) {
				$query .= ',' . PHP_EOL . $this->i() . $this->foreignKeyToString($fk);
			}
		$this->outdent();
		$query .= PHP_EOL . ")" . ";";

		return $query;
	}

	protected function tableColumnToString(TableColumn $column, bool $serial) {
		$result = $this->escapeIdentifier($column->getName());
		$result .= ' ' . $this->dataTypeToString($column->getType());

		if(!$column->isNullable()) {
			$result .= ' NOT NULL';
		}

		if($column->hasDefault()) {
			$result .= ' DEFAULT ' . $this->constantToString($column->getDefault());
		}

		return $result;
	}

	protected function dataTypeToString($type) {
		return $type;
	}

	protected function tablePrimaryColumnToString(Identifier $columnName, bool $serial) {
		return sprintf('PRIMARY KEY(%s)', $this->escapeIdentifier($columnName));
	}

	protected function uniqueIndexToString(array $columnNames) {
		return 'UNIQUE('. implode(', ', array_map(
			fn($name) => $this->escapeIdentifier($name),
			$columnNames
		)) .')';
	}

	protected function foreignKeyToString(ForeignKey $fk) {
		return 'FOREIGN KEY('. implode(', ', array_map(
			fn($name) => $this->escapeIdentifier($name),
			$fk->getOwnColumns()
		)) .')' . ' REFERENCES ' . $this->escapeIdentifier($fk->getForeignTable()) . 
		'(' . implode(', ', array_map(
			fn($name) => $this->escapeIdentifier($name),
			$fk->getTargetColumns()
		)) . ') ' . PHP_EOL . $this->i() . sprintf('ON UPDATE CASCADE ON DELETE %s', $fk->getOnDelete());
	}


	public function dropViewToString(CreateView $createView) {
		return "DROP VIEW IF EXISTS " . $this->escapeIdentifier($createView->getName()) . ";";
	}

	public function dropTableToString(CreateTable $createTable) {
		return "DROP TABLE IF EXISTS " . $this->escapeIdentifier($createTable->getName()) . ";";
	}

	abstract protected function escapeIdentifier(Identifier $identifier);

	protected function escapeLiteral(mixed $literal) {
		if($literal === NULL) {
			return 'NULL';
		} elseif (is_numeric($literal)) {
			return $literal;
		} elseif (is_bool($literal)) {
			return $literal ? '1' : '0';
		} else {
			$replacements = array(
		     "\x00"=>'\x00',
		     "\n"=>'\n',
		     "\r"=>'\r',
		     "\\"=>'\\\\',
		     "'"=>"''",
		     '"'=>'""',
		     "\x1a"=>'\x1a'
		  );
		  return "'".strtr($literal,$replacements)."'";
		}
	}
}