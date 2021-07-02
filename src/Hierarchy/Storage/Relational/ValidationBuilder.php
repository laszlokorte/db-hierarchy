<?php

namespace App\Hierarchy\Storage\Relational;


use App\Hierarchy\Schema\Definition\SchemaDefinition;

use App\Hierarchy\Storage\Relational\Algebra\TableReference;
use App\Hierarchy\Storage\Relational\Algebra\Identifier;
use App\Hierarchy\Storage\Relational\Algebra\Value\ColumnReference;
use App\Hierarchy\Storage\Relational\Algebra\Value\Constant;
use App\Hierarchy\Storage\Relational\Algebra\Value\Cases;
use App\Hierarchy\Storage\Relational\Algebra\Value\UnaryOperation;
use App\Hierarchy\Storage\Relational\Algebra\Value\BinaryOperation;
use App\Hierarchy\Storage\Relational\Algebra\Value\AssociativeOperation;
use App\Hierarchy\Storage\Relational\Algebra\Value\FunctionApplication;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Comparison\Equal;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Comparison\NotEqual;
use App\Hierarchy\Storage\Relational\Algebra\Value\Projected;
use App\Hierarchy\Storage\Relational\Algebra\Value\Parameter;
use App\Hierarchy\Storage\Relational\Algebra\Value\ElementOf;
use App\Hierarchy\Storage\Relational\Algebra\Value\Aggregation;
use App\Hierarchy\Storage\Relational\Algebra\Value\Selection;
use App\Hierarchy\Storage\Relational\Algebra\Value\Tuple;
use App\Hierarchy\Storage\Relational\Algebra\Value\DefaultValue;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Comparison\GreaterThan;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Comparison\LessThan;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Comparison\LessThanEqual;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Logic\Conjunction;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Logic\Disjunction;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Numeric\Addition;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Numeric\Subtraction;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Function\Coalesce;
use App\Hierarchy\Storage\Relational\Algebra\Aggregation\Maximum;
use App\Hierarchy\Storage\Relational\Algebra\Select;
use App\Hierarchy\Storage\Relational\Algebra\Join;
use App\Hierarchy\Storage\Relational\Algebra\Projection;
use App\Hierarchy\Storage\Relational\Algebra\Insert;
use App\Hierarchy\Storage\Relational\Algebra\Update;
use App\Hierarchy\Storage\Relational\Algebra\Delete;
use App\Hierarchy\Storage\Relational\Algebra\Setter;
use App\Hierarchy\Storage\Relational\Algebra\Order;

class ValidationBuilder {

	public function __construct(private SchemaDefinition $schemaDef, private Naming $naming, private ColumnCoder $coder) {
		
	}

	public function getSelectForScopeParentCheck($keyId, Parameter $scopeParam, Parameter $parentParam) {
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

	public function getSelectForUniquenessCheckNew($keyId, Parameter $scopeParam, Parameter $parentParam, $fieldsToCheck) {
		$table = new TableReference($this->naming->nodeTableName($keyId));
		$tableH = new TableReference($this->naming->hierarchyViewName($keyId));
		$conditions = [];
		$checks = [new Constant(0)];
		$projections = [];

		foreach ($fieldsToCheck as $fieldId => $params) {
			$columns = [];
			foreach($this->schemaDef->getKeyFieldColumns($keyId, $fieldId) AS $i => $column) {
				$columns[] = new ColumnReference($table, $this->naming->fieldColumnToName($column));
			}

			$checks[$fieldId] = new BinaryOperation(
				new Equal(),
				new Tuple($columns),
				new Tuple($params)
			);
		}

		$conditions[] = new AssociativeOperation(new Disjunction(), array_values($checks));

		if($this->schemaDef->isKeyScoped($keyId)) {
			$conditions[] = new BinaryOperation(new Equal(), 
				$this->coder->wrapScopeParameter($keyId, $scopeParam),
				new ColumnReference($tableH, $this->naming->hierarchyScopeColumnName($keyId))
			);
		}

    	if($this->schemaDef->isKeyReflexive($keyId)) {
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

	public function getSelectForUniquenessCheckEdit($keyId, Parameter $idParam, $fieldsToCheck) {
		$table = new TableReference($this->naming->nodeTableName($keyId));
		$tableH = new TableReference($this->naming->hierarchyViewName($keyId), new Identifier('h1'));
		$tableH2 = new TableReference($this->naming->hierarchyViewName($keyId), new Identifier('h2'));
		$conditions = [];
		$checks = [new Constant(0)];
		$projections = [];

		foreach ($fieldsToCheck as $fieldId => $params) {
			$columns = [];
			foreach($this->schemaDef->getKeyFieldColumns($keyId, $fieldId) AS $i => $column) {
				$columns[] = new ColumnReference($table, $this->naming->fieldColumnToName($column));
			}

			$checks[$fieldId] = new BinaryOperation(
				new Equal(true),
				new Tuple($columns),
				new Tuple($params)
			);
		}

		$conditions[] = new AssociativeOperation(new Disjunction(), array_values($checks));

		$conditions[] = new BinaryOperation(new NotEqual(true), 
			$this->coder->wrapPrimaryKeyParameter($keyId, $idParam),
			new ColumnReference($tableH, $this->naming->hierarchyIdColumnName($keyId))
		);

		$conditions[] = new BinaryOperation(new Equal(), 
			$this->coder->wrapPrimaryKeyParameter($keyId, $idParam),
			new ColumnReference($tableH2, $this->naming->hierarchyIdColumnName($keyId))
		);

		foreach ($checks as $fieldId => $check) {
			$projections[] = new Projection(new Aggregation(new Maximum(), $check), new Identifier($fieldId));
		}

		return new Select(
			$projections,
			[$table], [new Join($tableH, new BinaryOperation(
				new Equal(), 
				new ColumnReference($tableH, $this->naming->hierarchyIdColumnName($keyId)),
				new ColumnReference($table, $this->naming->nodeTablePKName($keyId))
			)), new Join($tableH2, new BinaryOperation(
				new Equal(true), 
				new Tuple([
					new ColumnReference($tableH2, $this->naming->hierarchyParentColumnName($keyId)),
					new ColumnReference($tableH2, $this->naming->hierarchyScopeColumnName($keyId)),
				]),
				new Tuple([
					new ColumnReference($tableH, $this->naming->hierarchyParentColumnName($keyId)),
					new ColumnReference($tableH, $this->naming->hierarchyScopeColumnName($keyId)),
				])
			))],
			new AssociativeOperation(new Conjunction(), $conditions)
		);
	}

}
