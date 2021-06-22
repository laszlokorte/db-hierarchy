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
use App\Hierarchy\Storage\Relational\Algebra\Value\BinaryOperation;
use App\Hierarchy\Storage\Relational\Algebra\Value\ColumnReference;
use App\Hierarchy\Storage\Relational\Algebra\Value\Parameter;
use App\Hierarchy\Storage\Relational\Algebra\Value\Constant;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Comparison\NotEqual;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Comparison\Equal;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Logic\Conjunction;

class QueryBuilder {
	public function __construct(private SchemaDefinition $schemaDef, private Naming $naming) {
		
	}

	public function getSelectForFindRootNodes(string $keyId) {
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
				new Constant(null)
			),
			new BinaryOperation(
				new Equal(TRUE),
				new ColumnReference($tableH, $this->naming->hierarchyParentColumnName($keyId)),
				new Constant(null)
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

	public function getSelectForFindNodeField(string $keyId, string $fieldId) {
		
	}

	public function getSelectForFindNodeChildren(string $keyId, string $childKeyId) {
		
	}

	public function getSelectForFindNodeReflexiveParents(string $keyId) {
		$closureTable = new TableReference($this->naming->closureTableName($keyId));

		$condition = new BinaryOperation(
			new Equal(),
			new ColumnReference($closureTable, $this->naming->closureChildColumnName($keyId)),
			new Parameter('_id')
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
