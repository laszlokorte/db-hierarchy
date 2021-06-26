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

class Sqlite extends SqlBase implements DialectInterface {
	protected function foreignKeyToString(ForeignKey $fk) {
		return parent::foreignKeyToString($fk) . ' DEFERRABLE INITIALLY DEFERRED';
	}

	protected function escapeIdentifier(Identifier $identifier) {
		return sprintf('"%s"', str_replace(['/','"'], '', $identifier->getString()));
	}

	protected function tablePrimaryColumnToString(Identifier $columnName, bool $serial) {
		return sprintf(
			'PRIMARY KEY(%s%s)', 
			$this->escapeIdentifier($columnName),
			$serial ? ' AUTOINCREMENT' : ''
		);
	}

	protected function operatorSymbol($operator) {
		switch(get_class($operator)) {
			case Operator\Comparison\Equal::class:
				if($operator->allowNull()) {
					return 'IS';
				} else {
					return '=';
				}
			case Operator\Comparison\NotEqual::class:
				if($operator->allowNull()) {
					return 'IS NOT';
				} else {
					return '<>';
				}
			case Operator\String\Concat::class:
				return '||';
			default:
				return parent::operatorSymbol($operator);
		}
	}

}