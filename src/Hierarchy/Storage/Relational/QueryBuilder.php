<?php

namespace App\Hierarchy\Storage\Relational;


/*
getParentKey
loadAllClosureDefects
loadAllKeyNodes
loadAllRowOrder
loadChildKeyNodes
loadHierarchy
loadMoveTargets
loadNode
loadNodeField
loadNodesDirectParent
loadRootKeyNodes
loadRootNodes
*/
use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Schema\Definition\StorageCoding;

use App\Hierarchy\Storage\Relational\Algebra\Identifier;
use App\Hierarchy\Storage\Relational\Algebra\Select;
use App\Hierarchy\Storage\Relational\Algebra\Projection;
use App\Hierarchy\Storage\Relational\Algebra\TableReference;
use App\Hierarchy\Storage\Relational\Algebra\Order;
use App\Hierarchy\Storage\Relational\Algebra\Join;
use App\Hierarchy\Storage\Relational\Algebra\Value\ValueInterface;
use App\Hierarchy\Storage\Relational\Algebra\Value\BinaryOperation;
use App\Hierarchy\Storage\Relational\Algebra\Value\UnaryOperation;
use App\Hierarchy\Storage\Relational\Algebra\Value\AssociativeOperation;
use App\Hierarchy\Storage\Relational\Algebra\Value\ColumnReference;
use App\Hierarchy\Storage\Relational\Algebra\Value\Parameter;
use App\Hierarchy\Storage\Relational\Algebra\Value\Constant;
use App\Hierarchy\Storage\Relational\Algebra\Value\FunctionApplication;
use App\Hierarchy\Storage\Relational\Algebra\Value\Existence;
use App\Hierarchy\Storage\Relational\Algebra\Value\Tuple;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Comparison\NotEqual;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Comparison\Equal;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Logic\Conjunction;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Logic\Negation;
use App\Hierarchy\Storage\Relational\Algebra\Operator\String\Concat;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Function\Coalesce;

class QueryBuilder {
	public function __construct(private SchemaDefinition $schemaDef, private Naming $naming, private ColumnCoder $coder) {
		
	}

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

	public function getSelectForFindHierarchyCousins(string $keyId, Parameter|Constant $idParam) {
		$tableH = new TableReference($this->naming->hierarchyViewName($keyId));
		$tableN = new TableReference($this->naming->nodeTableName($keyId));
		$tableC = new TableReference($this->naming->closureTableName($keyId));

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

		$condition = new UnaryOperation(
			new Negation(),
			new Existence(new Select([new Projection($this->coder->wrapClosureParentColumn($keyId, $tableC))], [$tableC], [], 
				new BinaryOperation(
					new Equal(),
					new Tuple([
						new ColumnReference($tableC, $this->naming->closureParentColumnName($keyId)),
						new ColumnReference($tableC, $this->naming->closureChildColumnName($keyId))
					]),
					new Tuple([
						$this->coder->wrapPrimaryKeyParameter($keyId, $idParam),
						new ColumnReference($tableH, $this->naming->hierarchyIdColumnName($keyId)),
					]),
				)
			))
		);


		foreach ($this->schemaDef->getKeyFieldIds($keyId) as $fieldId) {
			foreach ($this->schemaDef->getKeyFieldColumns($keyId, $fieldId) AS $column) {
				$projections[] = new Projection($this->coder->wrapColumn($column, $tableN), new Identifier($column->getName()));
			}
		}

		return new Select($projections, [$tableN], $joins, $condition);
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

	public function getSelectForFindNodeChildren(string $keyId, string $childKeyId) {
		
	}

	public function getSelectForFindNodeReflexiveParents(string $keyId, Parameter|Constant $id) {
		$closureTable = new TableReference($this->naming->closureTableName($keyId));

		$condition = new BinaryOperation(
			new Equal(),
			new ColumnReference($closureTable, $this->naming->closureChildColumnName($keyId)),
			$this->coder->wrapPrimaryKeyParameter($keyId, $id)
		);
		$orders = [
			new Order(new ColumnReference($closureTable, $this->naming->closureTableDepthName($keyId)), true)
		];

		return new Select([
			new Projection($this->coder->wrapClosureParentColumn($keyId, $closureTable)),
		], [$closureTable], [], $condition, $orders);
	}

	public function getSelectForFindReflexiveParentNodes(string $keyId, Parameter|Constant $id) {
		$tableN = new TableReference($this->naming->nodeTableName($keyId));
		$tableC = new TableReference($this->naming->closureTableName($keyId));

		$projections = [];

		$projections[] = new Projection($this->coder->wrapPrimaryColumn($keyId, $tableN), $this->naming->hierarchyIdColumnName($keyId));
		$projections[] = new Projection($this->coder->wrapClosureParentColumn($keyId, $tableC), $this->naming->hierarchyParentColumnName($keyId));

		if($this->schemaDef->isKeyScoped($keyId)) {
			$projections[] = new Projection($this->coder->wrapHierarchyScopeColumn($keyId, $tableN), $this->naming->hierarchyScopeColumnName($keyId));
		} else {
			$projections[] = new Projection(new Constant(null), $this->naming->hierarchyScopeColumnName($keyId));
		}

		$joins = [];
		$joins[] = new Join($tableC, new BinaryOperation(
			new Equal(),
			new ColumnReference($tableC, $this->naming->closureParentColumnName($keyId)),
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

	public function getDiagnosableKeys() {
		return array_filter($this->schemaDef->getAllKeyIdsTopological(), 
			fn($keyId) => $this->schemaDef->isKeyReflexive($keyId) || $this->schemaDef->isKeyOrdered($keyId)
		);
	}

	public function getDiagnosisQueriesForKey($keyId) {
		$result = [];

		if($this->schemaDef->isKeyReflexive($keyId)) {
			$result['missing'] = $this->getSelectForFindKeyClosureMissings($keyId);
			$result['invalid'] = $this->getSelectForFindKeyClosureInvalids($keyId);
		}

		if($this->schemaDef->isKeyOrdered($keyId)) {
			$result['order'] = $this->getSelectForFindKeyOrderNotNormalized($keyId);
		}

		return $result;
	}

	public function getSelectForFindKeyClosureMissings($keyId) {
		$missingView = new TableReference($this->naming->closureMissingViewName($keyId));
		return new Select([
			new Projection(
				$this->coder->decodeColumnType(
					SchemaBuilder::CLOSURE_TABLE_PK_TYPE,
					new ColumnReference($missingView, $this->naming->closureMissingIdColumn($keyId))
				),
				$this->naming->closureMissingIdColumn($keyId)
			),
			new Projection(
				$this->coder->decodeColumnType(
					$this->schemaDef->getKeyIdentityColumnType($keyId),
					new ColumnReference($missingView, $this->naming->closureMissingParentColumn($keyId))
				),
				$this->naming->closureMissingParentColumn($keyId)
			),
			new Projection(
				$this->coder->decodeColumnType(
					$this->schemaDef->getKeyIdentityColumnType($keyId),
					new ColumnReference($missingView, $this->naming->closureMissingChildColumn($keyId))
				),
				$this->naming->closureMissingChildColumn($keyId)
			),
			new Projection(
				$this->coder->decodeColumnType(
					SchemaBuilder::CLOSURE_TABLE_DEPTH_TYPE,
					new ColumnReference($missingView, $this->naming->closureMissingDepthColumn($keyId))
				),
				$this->naming->closureMissingDepthColumn($keyId)
			),
			new Projection(
				$this->coder->decodeColumnType(
					'VARCHAR',
					new ColumnReference($missingView, $this->naming->closureMissingReasonColumn($keyId))
				),
				$this->naming->closureMissingReasonColumn($keyId)
			),
		], [$missingView]);
	}

	public function getSelectForFindKeyClosureInvalids($keyId) {
		$invalidView = new TableReference($this->naming->closureInvalidViewName($keyId));
		return new Select([
			new Projection(
				$this->coder->decodeColumnType(
					SchemaBuilder::CLOSURE_TABLE_PK_TYPE,
					new ColumnReference($invalidView, $this->naming->closureInvalidIdColumn($keyId))
				),
				$this->naming->closureInvalidIdColumn($keyId)
			),
			new Projection(
				$this->coder->decodeColumnType(
					$this->schemaDef->getKeyIdentityColumnType($keyId),
					new ColumnReference($invalidView, $this->naming->closureInvalidParentColumn($keyId))
				),
				$this->naming->closureInvalidParentColumn($keyId)
			),
			new Projection(
				$this->coder->decodeColumnType(
					$this->schemaDef->getKeyIdentityColumnType($keyId),
					new ColumnReference($invalidView, $this->naming->closureInvalidChildColumn($keyId))
				),
				$this->naming->closureInvalidChildColumn($keyId)
			),
			new Projection(
				$this->coder->decodeColumnType(
					SchemaBuilder::CLOSURE_TABLE_DEPTH_TYPE,
					new ColumnReference($invalidView, $this->naming->closureInvalidDepthColumn($keyId))
				),
				$this->naming->closureInvalidDepthColumn($keyId)
			),
		], [$invalidView]);
	}

	public function getSelectForFindKeyOrderNotNormalized(string $keyId) {
		$orderView = new TableReference($this->naming->normalizedOrderViewName($keyId));
		$orderCondition = new BinaryOperation(
			new NotEqual(),
			new ColumnReference($orderView, $this->naming->normalizedOrderNormalizedColumnName($keyId)),
			new ColumnReference($orderView, $this->naming->normalizedOrderStoredColumnName($keyId))
		);

		return new Select([
			new Projection(
				$this->coder->decodeColumnType(
					StorageCoding::INTEGER,
					new ColumnReference($orderView, $this->naming->normalizedOrderStoredColumnName($keyId))
				),
				$this->naming->normalizedOrderStoredColumnName($keyId)
			),
			new Projection(
				$this->coder->decodeColumnType(
					StorageCoding::INTEGER,
					new ColumnReference($orderView, $this->naming->normalizedOrderNormalizedColumnName($keyId))
				),
				$this->naming->normalizedOrderNormalizedColumnName($keyId)
			),
			new Projection(
				$this->coder->decodeColumnType(
					$this->schemaDef->getKeyIdentityColumnType($keyId),
					new ColumnReference($orderView, $this->naming->normalizedOrderIdColumnName($keyId))
				),
				$this->naming->normalizedOrderIdColumnName($keyId)
			),
			new Projection(
				$this->coder->decodeColumnType(
					$this->schemaDef->getKeyIdentityColumnType($keyId),
					new ColumnReference($orderView, $this->naming->normalizedOrderParentColumnName($keyId))
				),
				$this->naming->normalizedOrderParentColumnName($keyId)
			),
			new Projection(
				$this->schemaDef->isKeyScoped($keyId)?
					$this->coder->decodeColumnType(
						$this->schemaDef->getKeyScopeColumnType($keyId),
						new ColumnReference($orderView, $this->naming->normalizedOrderScopeColumnName($keyId))
					) :
				new ColumnReference($orderView, $this->naming->normalizedOrderScopeColumnName($keyId)),
				$this->naming->normalizedOrderScopeColumnName($keyId)

			),
		], [$orderView], [], $orderCondition);
	}

	public function getSelectForFindDefectsForNode(string $keyId) {

	}
}
