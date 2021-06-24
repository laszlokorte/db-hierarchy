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
	public function __construct(private SchemaDefinition $schemaDef, private Naming $naming) {
		
	}

	public function getSelectForFindNodes(string $keyId, ValueInterface $scope, ValueInterface $parent) {
		$tableH = new TableReference($this->naming->hierarchyViewName($keyId));
		$tableN = new TableReference($this->naming->nodeTableName($keyId));

		$projections = [];
		$projections[] = new Projection(new ColumnReference($tableH, $this->naming->hierarchyIdColumnName($keyId)), $this->naming->hierarchyIdColumnName($keyId));
		$projections[] = new Projection(new ColumnReference($tableH, $this->naming->hierarchyOrderColumnName($keyId)), $this->naming->hierarchyOrderColumnName($keyId));
		$projections[] = new Projection(new ColumnReference($tableH, $this->naming->hierarchyParentColumnName($keyId)), $this->naming->hierarchyParentColumnName($keyId));
		$projections[] = new Projection(new ColumnReference($tableH, $this->naming->hierarchyScopeColumnName($keyId)), $this->naming->hierarchyScopeColumnName($keyId));

		$joins = [];
		$joins[] = new Join($tableH, new BinaryOperation(
			new Equal(),
			new ColumnReference($tableH, $this->naming->hierarchyIdColumnName($keyId)),
			new ColumnReference($tableN, $this->naming->nodeTablePKName($keyId))
		));

		$condition = new BinaryOperation(
			new Conjunction(),
			new BinaryOperation(
				new Equal(TRUE),
				new ColumnReference($tableH, $this->naming->hierarchyScopeColumnName($keyId)),
				$scope
			),
			new BinaryOperation(
				new Equal(TRUE),
				new ColumnReference($tableH, $this->naming->hierarchyParentColumnName($keyId)),
				$parent
			)
		);

		foreach ($this->schemaDef->getKeyFieldIds($keyId) as $fieldId) {
			$type = $this->schemaDef->getKeyFieldType($keyId, $fieldId);
			$options  = $this->schemaDef->getKeyFieldOptions($keyId, $fieldId);
			$columns = $type->getColumns($fieldId, $options);

			foreach ($columns AS $column) {
				$projections[] = new Projection(new ColumnReference($tableN, new Identifier($column->getName())), new Identifier($column->getName()));
			}
		}

		return new Select($projections, [$tableN], $joins, $condition);
	}

	public function getSelectForFindHierarchy(string $keyId, ?ValueInterface $scope, ?ValueInterface $parent) {
		$tableH = new TableReference($this->naming->hierarchyViewName($keyId));
		$tableN = new TableReference($this->naming->nodeTableName($keyId));

		$projections = [];
		$projections[] = new Projection(new AssociativeOperation(
			new Concat(), [
			new FunctionApplication(new Coalesce(),  [
				new ColumnReference($tableH, $this->naming->hierarchyScopeColumnName($keyId)),
				new Constant('-'),
			]),
			new Constant('/'),
			new FunctionApplication(new Coalesce(), [
				new ColumnReference($tableH, $this->naming->hierarchyParentColumnName($keyId)),
				new Constant('-'),
			]),
		]), new Identifier('_treeIndex'));
		$projections[] = new Projection(new ColumnReference($tableH, $this->naming->hierarchyIdColumnName($keyId)), $this->naming->hierarchyIdColumnName($keyId));
		$projections[] = new Projection(new ColumnReference($tableH, $this->naming->hierarchyOrderColumnName($keyId)), $this->naming->hierarchyOrderColumnName($keyId));
		$projections[] = new Projection(new ColumnReference($tableH, $this->naming->hierarchyParentColumnName($keyId)), $this->naming->hierarchyParentColumnName($keyId));
		$projections[] = new Projection(new ColumnReference($tableH, $this->naming->hierarchyScopeColumnName($keyId)), $this->naming->hierarchyScopeColumnName($keyId));

		$joins = [];
		$joins[] = new Join($tableH, new BinaryOperation(
			new Equal(),
			new ColumnReference($tableH, $this->naming->hierarchyIdColumnName($keyId)),
			new ColumnReference($tableN, $this->naming->nodeTablePKName($keyId))
		));

		$condition = new Constant(1);

		foreach ($this->schemaDef->getKeyFieldIds($keyId) as $fieldId) {
			$type = $this->schemaDef->getKeyFieldType($keyId, $fieldId);
			$options  = $this->schemaDef->getKeyFieldOptions($keyId, $fieldId);
			$columns = $type->getColumns($fieldId, $options);

			foreach ($columns AS $column) {
				$projections[] = new Projection(new ColumnReference($tableN, new Identifier($column->getName())), new Identifier($column->getName()));
			}
		}

		return new Select($projections, [$tableN], $joins, $condition);
	}

	public function getSelectForFindHierarchyCousins(string $keyId, ValueInterface $idParam) {
		$tableH = new TableReference($this->naming->hierarchyViewName($keyId));
		$tableN = new TableReference($this->naming->nodeTableName($keyId));
		$tableC = new TableReference($this->naming->closureTableName($keyId));

		$projections = [];
		$projections[] = new Projection(new AssociativeOperation(
			new Concat(), [
			new FunctionApplication(new Coalesce(),  [
				new ColumnReference($tableH, $this->naming->hierarchyScopeColumnName($keyId)),
				new Constant('-'),
			]),
			new Constant('/'),
			new FunctionApplication(new Coalesce(), [
				new ColumnReference($tableH, $this->naming->hierarchyParentColumnName($keyId)),
				new Constant('-'),
			]),
		]), new Identifier('_treeIndex'));
		$projections[] = new Projection(new ColumnReference($tableH, $this->naming->hierarchyIdColumnName($keyId)), $this->naming->hierarchyIdColumnName($keyId));
		$projections[] = new Projection(new ColumnReference($tableH, $this->naming->hierarchyOrderColumnName($keyId)), $this->naming->hierarchyOrderColumnName($keyId));
		$projections[] = new Projection(new ColumnReference($tableH, $this->naming->hierarchyParentColumnName($keyId)), $this->naming->hierarchyParentColumnName($keyId));
		$projections[] = new Projection(new ColumnReference($tableH, $this->naming->hierarchyScopeColumnName($keyId)), $this->naming->hierarchyScopeColumnName($keyId));

		$joins = [];
		$joins[] = new Join($tableH, new BinaryOperation(
			new Equal(),
			new ColumnReference($tableH, $this->naming->hierarchyIdColumnName($keyId)),
			new ColumnReference($tableN, $this->naming->nodeTablePKName($keyId))
		));

		$condition = new UnaryOperation(
			new Negation(),
			new Existence(new Select([new Projection(new ColumnReference($tableC, $this->naming->closureTablePkName($keyId)))], [$tableC], [], 
				new BinaryOperation(
					new Equal(),
					new Tuple([
						new ColumnReference($tableC, $this->naming->closureParentColumnName($keyId)),
						new ColumnReference($tableC, $this->naming->closureChildColumnName($keyId))
					]),
					new Tuple([
						$idParam,
						new ColumnReference($tableH, $this->naming->hierarchyIdColumnName($keyId)),
					]),
				)
			))
		);


		foreach ($this->schemaDef->getKeyFieldIds($keyId) as $fieldId) {
			$type = $this->schemaDef->getKeyFieldType($keyId, $fieldId);
			$options  = $this->schemaDef->getKeyFieldOptions($keyId, $fieldId);
			$columns = $type->getColumns($fieldId, $options);

			foreach ($columns AS $column) {
				$projections[] = new Projection(new ColumnReference($tableN, new Identifier($column->getName())), new Identifier($column->getName()));
			}
		}

		return new Select($projections, [$tableN], $joins, $condition);
	}

	public function getSelectForFindNode(string $keyId, Parameter $parameter) {
		$tableH = new TableReference($this->naming->hierarchyViewName($keyId));
		$tableN = new TableReference($this->naming->nodeTableName($keyId));

		$projections = [];
		$projections[] = new Projection(new ColumnReference($tableH, $this->naming->hierarchyIdColumnName($keyId)), $this->naming->hierarchyIdColumnName($keyId));
		$projections[] = new Projection(new ColumnReference($tableH, $this->naming->hierarchyOrderColumnName($keyId)), $this->naming->hierarchyOrderColumnName($keyId));
		$projections[] = new Projection(new ColumnReference($tableH, $this->naming->hierarchyParentColumnName($keyId)), $this->naming->hierarchyParentColumnName($keyId));
		$projections[] = new Projection(new ColumnReference($tableH, $this->naming->hierarchyScopeColumnName($keyId)), $this->naming->hierarchyScopeColumnName($keyId));

		$joins = [];
		$joins[] = new Join($tableH, new BinaryOperation(
			new Equal(),
			new ColumnReference($tableH, $this->naming->hierarchyIdColumnName($keyId)),
			new ColumnReference($tableN, $this->naming->nodeTablePKName($keyId))
		));

		$condition = new BinaryOperation(
			new Equal(),
			new ColumnReference($tableN, $this->naming->nodeTablePKName($keyId)),
			$parameter
		);

		foreach ($this->schemaDef->getKeyFieldIds($keyId) as $fieldId) {
			$type = $this->schemaDef->getKeyFieldType($keyId, $fieldId);
			$options  = $this->schemaDef->getKeyFieldOptions($keyId, $fieldId);
			$columns = $type->getColumns($fieldId, $options);

			foreach ($columns AS $column) {
				$projections[] = new Projection(new ColumnReference($tableN, new Identifier($column->getName())), new Identifier($column->getName()));
			}
		}

		return new Select($projections, [$tableN], $joins, $condition);
	}

	public function getSelectForFindNodeField(string $keyId, string $fieldId, $idParam) {
		$tableN = new TableReference($this->naming->nodeTableName($keyId));

		$projections = [];

		$condition = new BinaryOperation(
			new Equal(),
			new ColumnReference($tableN, $this->naming->nodeTablePKName($keyId)),
			$idParam
		);

		$type = $this->schemaDef->getKeyFieldType($keyId, $fieldId);
		$options  = $this->schemaDef->getKeyFieldOptions($keyId, $fieldId);
		$columns = $type->getColumns($fieldId, $options);

		foreach ($columns AS $column) {
			$projections[] = new Projection(new ColumnReference($tableN, new Identifier($column->getName())), new Identifier($column->getName()));
		}

		return new Select($projections, [$tableN], [], $condition);
	}

	public function getSelectForFindNodeChildren(string $keyId, string $childKeyId) {
		
	}

	public function getSelectForFindNodeReflexiveParents(string $keyId, ValueInterface $id) {
		$closureTable = new TableReference($this->naming->closureTableName($keyId));

		$condition = new BinaryOperation(
			new Equal(),
			new ColumnReference($closureTable, $this->naming->closureChildColumnName($keyId)),
			$id
		);
		$orders = [
			new Order(new ColumnReference($closureTable, $this->naming->closureTableDepthName($keyId)), true)
		];

		return new Select([
			new Projection(new ColumnReference($closureTable, $this->naming->closureParentColumnName($keyId))),
		], [$closureTable], [], $condition, $orders);
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
			new Projection(new ColumnReference($missingView, $this->naming->closureMissingIdColumn($keyId))),
			new Projection(new ColumnReference($missingView, $this->naming->closureMissingParentColumn($keyId))),
			new Projection(new ColumnReference($missingView, $this->naming->closureMissingChildColumn($keyId))),
			new Projection(new ColumnReference($missingView, $this->naming->closureMissingDepthColumn($keyId))),
			new Projection(new ColumnReference($missingView, $this->naming->closureMissingReasonColumn($keyId))),
		], [$missingView]);
	}

	public function getSelectForFindKeyClosureInvalids($keyId) {
		$invalidView = new TableReference($this->naming->closureInvalidViewName($keyId));
		return new Select([
			new Projection(new ColumnReference($invalidView, $this->naming->closureInvalidIdColumn($keyId))),
			new Projection(new ColumnReference($invalidView, $this->naming->closureInvalidParentColumn($keyId))),
			new Projection(new ColumnReference($invalidView, $this->naming->closureInvalidChildColumn($keyId))),
			new Projection(new ColumnReference($invalidView, $this->naming->closureInvalidDepthColumn($keyId))),
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
			new Projection(new ColumnReference($orderView, $this->naming->normalizedOrderStoredColumnName($keyId))),
			new Projection(new ColumnReference($orderView, $this->naming->normalizedOrderNormalizedColumnName($keyId))),
			new Projection(new ColumnReference($orderView, $this->naming->normalizedOrderIdColumnName($keyId))),
			new Projection(new ColumnReference($orderView, $this->naming->normalizedOrderParentColumnName($keyId))),
			new Projection(new ColumnReference($orderView, $this->naming->normalizedOrderScopeColumnName($keyId))),
		], [$orderView], [], $orderCondition);
	}

	public function getSelectForFindDefectsForNode(string $keyId) {

	}
}
