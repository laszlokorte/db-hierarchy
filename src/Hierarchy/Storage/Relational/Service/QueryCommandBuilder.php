<?php

namespace App\Hierarchy\Storage\Relational\Service;

use App\Hierarchy\Storage\Relational\Naming;
use App\Hierarchy\Storage\Relational\ColumnCoder;
use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Storage\Relational\Algebra\Value\Parameter;
use App\Hierarchy\Storage\Relational\Algebra\Value\Constant;
use App\Hierarchy\Storage\Relational\Algebra\TableReference;
use App\Hierarchy\Storage\Relational\Algebra\Projection;
use App\Hierarchy\Storage\Relational\Algebra\Join;
use App\Hierarchy\Storage\Relational\Algebra\Identifier;
use App\Hierarchy\Storage\Relational\Algebra\Select;
use App\Hierarchy\Storage\Relational\Algebra\Order;
use App\Hierarchy\Storage\Relational\Algebra\Value\ColumnReference;
use App\Hierarchy\Storage\Relational\Algebra\Value\BinaryOperation;
use App\Hierarchy\Storage\Relational\Algebra\Value\AssociativeOperation;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Comparison\Equal;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Logic\Conjunction;
use App\Hierarchy\Storage\Relational\Algebra\Operator\String\Concat;
use App\Hierarchy\Storage\Relational\Algebra\Value\FunctionApplication;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Function\Coalesce;



class QueryCommandBuilder  {

	public function __construct(private SchemaDefinition $schemaDef, private Naming $naming, private ColumnCoder $coder) {
	}

	// getSelectForFindNodes
	// getSelectForFindHierarchy
	// getSelectForFindNode
	// getSelectForFindNodeField
	// getSelectForFindReflexiveParentNodes

	public function getSelectForFindNodes(string $keyId, null|Parameter|Constant $scope, null|Parameter|Constant $parent) {
		$tableH = new TableReference($this->naming->hierarchyViewName($keyId));
		$tableN = new TableReference($this->naming->nodeTableName($keyId));

		$projections = [];
		$projections[] = new Projection($this->coder->wrapHierarchyPrimaryColumn($keyId, $tableH), $this->naming->hierarchyIdColumnName($keyId));
		$projections[] = new Projection($this->coder->wrapHierarchyOrderColumn($keyId, $tableH), $this->naming->hierarchyOrderColumnName($keyId));
		$projections[] = new Projection($this->coder->wrapHierarchyParentColumn($keyId, $tableH), $this->naming->hierarchyParentColumnName($keyId));
		$projections[] = new Projection($this->coder->wrapHierarchyScopeColumn($keyId, $tableH), $this->naming->hierarchyScopeColumnName($keyId));

		$joins = [];
		$joins[] = new Join($tableH, new BinaryOperation(
			new Equal(),
			new ColumnReference($tableH, $this->naming->hierarchyIdColumnName($keyId)),
			new ColumnReference($tableN, $this->naming->nodeTablePKName($keyId))
		));

		$conditionFragments = [];

		if($scope !== null) {
			$conditionFragments[] = new BinaryOperation(
				new Equal(TRUE),
				new ColumnReference($tableH, $this->naming->hierarchyScopeColumnName($keyId)),
				$this->coder->wrapScopeParameter($keyId, $scope)
			);
		}

		if($parent !== null) {
			$conditionFragments[] = new BinaryOperation(
			new Equal(TRUE),
			new ColumnReference($tableH, $this->naming->hierarchyParentColumnName($keyId)),
			$this->coder->wrapParentParameter($keyId, $parent)
		);
		}
		
		
		if(empty($conditionFragments)) {
			$condition = NULL;
		} else {
			$condition = new AssociativeOperation(
				new Conjunction(), $conditionFragments
			);
		}

		foreach ($this->schemaDef->getKeyFieldIds($keyId) as $fieldId) {
			foreach ($this->schemaDef->getKeyFieldColumns($keyId, $fieldId) AS $column) {
				$projections[] = new Projection($this->coder->wrapColumn($column, $tableN), new Identifier($column->getName()));
			}
		}

		$orders = [
			new Order(new ColumnReference($tableH, $this->naming->hierarchyScopeColumnName($keyId)), true),
			new Order(new ColumnReference($tableH, $this->naming->hierarchyOrderColumnName($keyId)), false)
		];

		return new Select($projections, [$tableN], $joins, $condition, $orders);
	}

	public function getSelectForFindHierarchy(string $keyId, null|Parameter|Constant $scope, null|Parameter|Constant $parent) {
		$tableH = new TableReference($this->naming->hierarchyViewName($keyId));
		$tableN = new TableReference($this->naming->nodeTableName($keyId));

		$projections = [];
		$projections[] = new Projection(new AssociativeOperation(
			new Concat(), [
			new FunctionApplication(new Coalesce(),  [
				$this->coder->wrapHierarchyScopeColumn($keyId, $tableH),
				new Constant('-'),
			]),
			new Constant('/'),
			new FunctionApplication(new Coalesce(), [
				$this->coder->wrapHierarchyParentColumn($keyId, $tableH),
				new Constant('-'),
			]),
		]), new Identifier('_treeIndex'));
		$projections[] = new Projection($this->coder->wrapHierarchyPrimaryColumn($keyId, $tableH), $this->naming->hierarchyIdColumnName($keyId));
		$projections[] = new Projection($this->coder->wrapHierarchyOrderColumn($keyId, $tableH), $this->naming->hierarchyOrderColumnName($keyId));
		$projections[] = new Projection($this->coder->wrapHierarchyParentColumn($keyId, $tableH), $this->naming->hierarchyParentColumnName($keyId));
		$projections[] = new Projection($this->coder->wrapHierarchyScopeColumn($keyId, $tableH), $this->naming->hierarchyScopeColumnName($keyId));

		$joins = [];
		$joins[] = new Join($tableH, new BinaryOperation(
			new Equal(),
			new ColumnReference($tableH, $this->naming->hierarchyIdColumnName($keyId)),
			new ColumnReference($tableN, $this->naming->nodeTablePKName($keyId))
		));

		$condition = new Constant(1);

		foreach ($this->schemaDef->getKeyFieldIds($keyId) as $fieldId) {
			foreach ($this->schemaDef->getKeyFieldColumns($keyId, $fieldId) AS $column) {
				$projections[] = new Projection($this->coder->wrapColumn($column, $tableN), new Identifier($column->getName()));
			}
		}

		$orders = [
			new Order(new ColumnReference($tableH, $this->naming->hierarchyScopeColumnName($keyId)), true),
			new Order(new ColumnReference($tableH, $this->naming->hierarchyOrderColumnName($keyId)), false)
		];

		return new Select($projections, [$tableN], $joins, $condition, $orders);
	}

	public function getSelectForFindNode(string $keyId, Parameter|Constant $idParameter) {
		$tableH = new TableReference($this->naming->hierarchyViewName($keyId));
		$tableN = new TableReference($this->naming->nodeTableName($keyId));

		$projections = [];
		$projections[] = new Projection($this->coder->wrapHierarchyPrimaryColumn($keyId, $tableH), $this->naming->hierarchyIdColumnName($keyId));
		$projections[] = new Projection($this->coder->wrapHierarchyOrderColumn($keyId, $tableH), $this->naming->hierarchyOrderColumnName($keyId));
		$projections[] = new Projection($this->coder->wrapHierarchyParentColumn($keyId, $tableH), $this->naming->hierarchyParentColumnName($keyId));
		$projections[] = new Projection($this->coder->wrapHierarchyScopeColumn($keyId, $tableH), $this->naming->hierarchyScopeColumnName($keyId));

		$joins = [];
		$joins[] = new Join($tableH, new BinaryOperation(
			new Equal(),
			new ColumnReference($tableH, $this->naming->hierarchyIdColumnName($keyId)),
			new ColumnReference($tableN, $this->naming->nodeTablePKName($keyId))
		));

		$condition = new BinaryOperation(
			new Equal(),
			new ColumnReference($tableN, $this->naming->nodeTablePKName($keyId)),
			$this->coder->wrapPrimaryKeyParameter($keyId, $idParameter)
		);

		foreach ($this->schemaDef->getKeyFieldIds($keyId) as $fieldId) {
			foreach ($this->schemaDef->getKeyFieldColumns($keyId, $fieldId) AS $column) {
				$projections[] = new Projection($this->coder->wrapColumn($column, $tableN), new Identifier($column->getName()));
			}
		}

		return new Select($projections, [$tableN], $joins, $condition);
	}

	public function getSelectForFindNodeField(string $keyId, string $fieldId, Parameter|Constant $idParam) {
		$tableN = new TableReference($this->naming->nodeTableName($keyId));

		$projections = [];

		$condition = new BinaryOperation(
			new Equal(),
			new ColumnReference($tableN, $this->naming->nodeTablePKName($keyId)),
			$this->coder->wrapPrimaryKeyParameter($keyId, $idParam)
		);

		foreach ($this->schemaDef->getKeyFieldColumns($keyId, $fieldId) AS $column) {
			$projections[] = new Projection($this->coder->wrapColumn($column, $tableN), new Identifier($column->getName()));
		}

		return new Select($projections, [$tableN], [], $condition);
	}

	public function getSelectForFindReflexiveParentNodes(string $keyId, Parameter|Constant $id) {
		$tableN = new TableReference($this->naming->nodeTableName($keyId));
		$tableC = new TableReference($this->naming->closureTableName($keyId));
		$tableH = new TableReference($this->naming->hierarchyViewName($keyId));

		$projections = [];

		$projections[] = new Projection($this->coder->wrapPrimaryColumn($keyId, $tableN), $this->naming->hierarchyIdColumnName($keyId));
		$projections[] = new Projection($this->coder->wrapClosureParentColumn($keyId, $tableC), $this->naming->hierarchyParentColumnName($keyId));

		if($this->schemaDef->isKeyScoped($keyId)) {
			$projections[] = new Projection($this->coder->wrapHierarchyScopeColumn($keyId, $tableH), $this->naming->hierarchyScopeColumnName($keyId));
		} else {
			$projections[] = new Projection(new Constant(null), $this->naming->hierarchyScopeColumnName($keyId));
		}

		$joins = [];
		$joins[] = new Join($tableC, new BinaryOperation(
			new Equal(),
			new ColumnReference($tableC, $this->naming->closureParentColumnName($keyId)),
			new ColumnReference($tableN, $this->naming->nodeTablePKName($keyId))
		));

		$joins[] = new Join($tableH, new BinaryOperation(
			new Equal(),
			new ColumnReference($tableH, $this->naming->hierarchyIdColumnName($keyId)),
			new ColumnReference($tableN, $this->naming->nodeTablePKName($keyId))
		));

		$condition = new BinaryOperation(
			new Equal(),
			new ColumnReference($tableC, $this->naming->closureChildColumnName($keyId)),
			$this->coder->wrapPrimaryKeyParameter($keyId, $id)
		);

		foreach ($this->schemaDef->getKeyFieldIds($keyId) as $fieldId) {
			foreach ($this->schemaDef->getKeyFieldColumns($keyId, $fieldId) AS $column) {
				$projections[] = new Projection($this->coder->wrapColumn($column, $tableN), new Identifier($column->getName()));
			}
		}

		$orders = [
			new Order(new ColumnReference($tableC, $this->naming->closureTableDepthName($keyId)), false)
		];

		return new Select($projections, [$tableN], $joins, $condition, $orders);
	}

}