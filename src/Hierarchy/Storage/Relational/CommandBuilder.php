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

class CommandBuilder  {

	public function __construct(private SchemaDefinition $schemaDef, private Naming $naming) {
	}

	public function getCommandForCreateNode(string $keyId, $scopeParam, $parentParam) {
		$tableName = $this->naming->nodeTableName($keyId);

		$columns = [];
		$values = [];

		if($this->schemaDef->isKeyScoped($keyId)) {
			$columns[] = $this->naming->nodeOwnScopeColumnName($keyId);
			$values[] = $scopeParam; 
		}
		if($this->schemaDef->isKeyOrdered($keyId)) {
			$orderView = new TableReference($this->naming->normalizedOrderViewName($keyId));

			$orderCondition = new BinaryOperation(
				new Conjunction(),
				new BinaryOperation(
					new Equal(true),
					new ColumnReference($orderView, $this->naming->normalizedOrderScopeColumnName($keyId)),
					$scopeParam
				),
				new BinaryOperation(
					new Equal(true),
					new ColumnReference($orderView, $this->naming->normalizedOrderParentColumnName($keyId)),
					$parentParam
				)
			);

			$columns[] = $this->naming->orderColumnName($keyId);
			$values[] = new BinaryOperation(
				new Addition(),
				new FunctionApplication(
					new Coalesce(), [
						new Selection(
							new Select([
								new Projection(
								new Aggregation(
									new Maximum(), 
									new ColumnReference($orderView, $this->naming->normalizedOrderNormalizedColumnName($keyId))
								)
							)], [$orderView], [], $orderCondition)
						),
						new Constant(0)
				]),
				new Constant(1)
			);
		}

		foreach ($this->schemaDef->getKeyFieldIds($keyId) as $fieldId) {
			foreach($this->schemaDef->getKeyFieldColumns($keyId, $fieldId) AS $column) {
				$columns[] = $this->naming->fieldColumnToName($column);
				$values[] = new Parameter($column->getName());
			}
		}

		return new Insert(
			$tableName,
			$columns,
			[$values]
		);
	}

	public function getCommandForClosureInsert($keyId, $scopeParam, $parentParam, $childParam, $depthParam) {
		$closureTableName = $this->naming->closureTableName($keyId);
		$missingView = new TableReference($this->naming->closureMissingViewName($keyId));

		$targetColumns = [
			$this->naming->closureParentColumnName($keyId),
			$this->naming->closureChildColumnName($keyId),
			$this->naming->closureTableDepthName($keyId),
		];

		$sourceColumns = [
			$parentParam,
			$childParam,
			$depthParam,
		];

		if($this->schemaDef->isKeyScoped($keyId)) {
			$targetColumns[] = $this->naming->nodeOwnScopeColumnName($keyId);
			$sourceColumns[] = $scopeParam;
		}

		return new Insert(
			$closureTableName,
			$targetColumns,
			[$sourceColumns]
		);
	}

	public function getCommandForClosureParentInsert($keyId, $scopeParam, $parentParam, $childParam) {
		$closureTableName = $this->naming->closureTableName($keyId);
		$missingView = new TableReference($this->naming->closureMissingViewName($keyId));

		$closureTable = new TableReference($closureTableName);

		$targetColumns = [
			$this->naming->closureParentColumnName($keyId),
			$this->naming->closureChildColumnName($keyId),
			$this->naming->closureTableDepthName($keyId),
		];

		$projections = [
			new Projection(new ColumnReference($closureTable, $this->naming->closureParentColumnName($keyId))),
			new Projection($parentParam),
			new Projection(
				new BinaryOperation(
					new Addition(),
					new ColumnReference($closureTable, $this->naming->closureTableDepthName($keyId)),
					new Constant(1)
				)
			)
		];



		if($this->schemaDef->isKeyScoped($keyId)) {
			$targetColumns[] = $this->naming->nodeOwnScopeColumnName($keyId);
			$projections[] = new Projection($scopeParam);
		}


		$select = new Select($projections, [$closureTable], [], new BinaryOperation(
			new Equal(),
			new ColumnReference($closureTable, $this->naming->closureChildColumnName($keyId)),
			$childParam
		));

		return new Insert(
			$closureTableName,
			$targetColumns,
			$select
		);
	}

	public function getCommandForUpdateNode(string $keyId, $idParam) {
		$table = new TableReference($this->naming->nodeTableName($keyId));

		$setters = [];
		$values = [];

		foreach ($this->schemaDef->getKeyFieldIds($keyId) as $fieldId) {
			foreach($this->schemaDef->getKeyFieldColumns($keyId, $fieldId) AS $column) {
				$setters[] = new Setter(
					new ColumnReference($table, $this->naming->fieldColumnToName($column)),
					new Parameter($column->getName())
				);
			}
		}

		$condition = new BinaryOperation(
			new Equal(),
			new ColumnReference($table, $this->naming->nodeTablePKName($keyId)),
			$idParam
		);

		return new Update(
			$table,
			$setters,
			$condition
		);
	}

	public function getCommandForDeleteMultipleNodes(string $keyId, $idParams) {
		$table = new TableReference($this->naming->nodeTableName($keyId));

		$condition = new ElementOf(
			new ColumnReference($table, $this->naming->nodeTablePKName($keyId)),
			$idParams
		);

		return new Delete(
			$table,
			$condition
		);
	}

	public function getSelectForCollectChildByIdReflexive(string $keyId, $idParams) {
		$closureTable = new TableReference($this->naming->closureTableName($keyId));

		$condition = new ElementOf(
			new ColumnReference($closureTable, $this->naming->closureParentColumnName($keyId)),
			$idParams
		);

		return new Select([
			new Projection(new ColumnReference($closureTable, $this->naming->closureChildColumnName($keyId)))
		], [$closureTable], [], $condition);
	}

	public function getSelectForCollectChildByScopeReflexive(string $keyId, $idParams) {
		$closureTable = new TableReference($this->naming->closureTableName($keyId));

		$condition = new ElementOf(
			new ColumnReference($closureTable, $this->naming->nodeOwnScopeColumnName($keyId)),
			$idParams
		);
		
		return new Select([
			new Projection(new ColumnReference($closureTable, $this->naming->closureChildColumnName($keyId)))
		], [$closureTable], [], $condition);
	}

	public function getSelectForCollectChildByScope(string $keyId, $idParams) {
		$nodeTable = new TableReference($this->naming->nodeTableName($keyId));

		$condition = new ElementOf(
			new ColumnReference($nodeTable, $this->naming->nodeOwnScopeColumnName($keyId)),
			$idParams
		);
		
		return new Select([
			new Projection(new ColumnReference($nodeTable, $this->naming->nodeTablePKName($keyId)))
		], [$nodeTable], [], $condition);
	}

	public function getSelectForReferencedNodes(string $keyId, $columns, $idParams) {
		$nodeTable = new TableReference($this->naming->nodeTableName($keyId));

		$conditions = [];

		foreach ($columns as $col) {
			$conditions[] = new ElementOf(
				new ColumnReference($nodeTable, $this->naming->fieldColumnToName($col)),
				$idParams
			);
		}
		
		return new Select([
			new Projection(new ColumnReference($nodeTable, $this->naming->nodeTablePKName($keyId)))
		], [$nodeTable], [], new AssociativeOperation(new Disjunction(), $conditions));
	}


	public function getSelectForMoveTargetExists($keyId, $scopeParam, $parentParam) {
		$table = new TableReference($this->naming->nodeTableName($keyId));
		// SELECT 1 FROM %s WHERE %s_id = :scope AND id = :id
		return new Select(
			[new Projection(new Constant(1))],
			[$table], [],
			new BinaryOperation(
				new Equal(),
				new Tuple([
					new ColumnReference($table, $this->naming->nodeOwnScopeColumnName($keyId)),
					new ColumnReference($table, $this->naming->nodeTablePKName($keyId)),
				]),
				new Tuple([$scopeParam, $parentParam])
			)
		);
	}

	public function getSelectForMoveTargetValid($keyId, $idParam, $parentParam) {
		$closureTable = new TableReference($this->naming->closureTableName($keyId));
		// SELECT 1 FROM %s WHERE parent_id = :id AND child_id=:newParent
		return new Select(
			[new Projection(new Constant(1))],
			[$closureTable], [],
			new BinaryOperation(
				new Equal(),
				new Tuple([
					new ColumnReference($closureTable, $this->naming->closureParentColumnName($keyId)),
					new ColumnReference($closureTable, $this->naming->closureChildColumnName($keyId)),
				]),
				new Tuple([$idParam, $parentParam])
			)
		);
	}

	public function getUpdateForMoveOwnScope($keyId, $idParam, $scopeParam) {
		$table = new TableReference($this->naming->nodeTableName($keyId));
		// UPDATE %s SET %s_id = :parentId WHERE id = :id
		return new Update(
			$table, [
				new Setter(
					new ColumnReference($table, $this->naming->nodeOwnScopeColumnName($keyId)),
					$scopeParam
				)
			],
			new BinaryOperation(
				new Equal(), 
				new ColumnReference($table, $this->naming->nodeTablePKName($keyId)),
				$idParam
			)
		);
	}

	public function getUpdateForMoveClosureScope($keyId, $idParam, $scopeParam) {
		$table = new TableReference($this->naming->nodeTableName($keyId));
		$closureTable = new TableReference($this->naming->closureTableName($keyId));
		// UPDATE %s SET %s_id = :parentId WHERE id = :id
		return new Update(
			$table, [
				new Setter(
					new ColumnReference($table, $this->naming->nodeOwnScopeColumnName($keyId)),
					$scopeParam
				)
			],
			new ElementOf(
				new ColumnReference($table, $this->naming->nodeTablePKName($keyId)),
				new Select([
					new Projection(
						new ColumnReference($closureTable, $this->naming->closureChildColumnName($keyId))
					)
				], [$closureTable], [], new BinaryOperation(
					new Equal(),
					new ColumnReference($closureTable, $this->naming->closureParentColumnName($keyId)),
					$idParam
				))
			)
		);
	}

	public function getUpdateForMoveClosureParents($keyId, $idParam, $scopeParam) {
		$table = new TableReference($this->naming->nodeTableName($keyId));
		$closureTable = new TableReference($this->naming->closureTableName($keyId));
		return new Update(
			$closureTable, [
				new Setter(
					new ColumnReference($closureTable, $this->naming->nodeOwnScopeColumnName($keyId)),
					$scopeParam
				)
			],
			new ElementOf(
				new ColumnReference($closureTable, $this->naming->closureParentColumnName($keyId)),
				new Select([
					new Projection(
						new ColumnReference($closureTable, $this->naming->closureChildColumnName($keyId))
					)
				], [$closureTable], [], new BinaryOperation(
					new Equal(),
					new ColumnReference($closureTable, $this->naming->closureParentColumnName($keyId)),
					$idParam
				))
			)
		);
	}

	public function getDeleteForMoveClosureOldParents($keyId, $idParam) {
		
		// IDEA:
		// DROP all transitive edges pointing higher than nodeId AND
		// CREATE transitive edges for all combinations of
		// edges pointing TO nodeId x edges pointing FROM targetParentId

		// DELETE FROM site_closure WHERE id IN (
		// SELECT bad.id FROM site_closure ok 
		// LEFT JOIN site_closure bad ON bad.child_id=ok.child_id
		// WHERE ok.parent_id=3 and ok.depth < bad.depth
		// );

		$closureTable = new TableReference($this->naming->closureTableName($keyId));
		$closureTableOk = new TableReference($this->naming->closureTableName($keyId), new Identifier('ok'));
		$closureTableBad = new TableReference($this->naming->closureTableName($keyId), new Identifier('bad'));

		return new Delete($closureTable, new ElementOf(
			new ColumnReference($closureTable, $this->naming->closureTablePkName($keyId)),
			new Select([new Projection(
				new ColumnReference($closureTableBad, $this->naming->closureTablePkName($keyId))
			)], [$closureTableOk], [new Join($closureTableBad, new BinaryOperation(
				new Equal(),
				new ColumnReference($closureTableOk, $this->naming->closureChildColumnName($keyId)),
				new ColumnReference($closureTableBad, $this->naming->closureChildColumnName($keyId))
			), 'LEFT')], new BinaryOperation(
				new Conjunction(),
				new BinaryOperation(
					new Equal(), 
					new ColumnReference($closureTableOk, $this->naming->closureParentColumnName($keyId)),
					$idParam,
				),
				new BinaryOperation(
					new LessThan(),
					new ColumnReference($closureTableOk, $this->naming->closureTableDepthName($keyId)),
					new ColumnReference($closureTableBad, $this->naming->closureTableDepthName($keyId)),
				)
			))
		));
	}

	public function getInsertForMoveClosureParents($keyId, $idParam, $scopeParam, $parentParam) {

		// INSERT INTO site_closure(child_id, parent_id, depth)
		// SELECT low.child_id,
		// high.parent_id, 
		// low.depth + high.depth + 1
		// FROM site_closure low, site_closure high
		//  WHERE low.parent_id = 3 AND high.child_id=5;
		$closureTableName = $this->naming->closureTableName($keyId);
		$closureTableLow = new TableReference($this->naming->closureTableName($keyId), new Identifier('low'));
		$closureTableHigh = new TableReference($this->naming->closureTableName($keyId), new Identifier('high'));

		$columns = [
			$this->naming->closureParentColumnName($keyId),
			$this->naming->closureChildColumnName($keyId),
			$this->naming->closureTableDepthName($keyId)
		];

		$projections = [
			new Projection(new ColumnReference($closureTableHigh, $this->naming->closureParentColumnName($keyId))),
			new Projection(new ColumnReference($closureTableLow, $this->naming->closureChildColumnName($keyId))),
			new Projection(new AssociativeOperation(
				new Addition(),[
				new ColumnReference($closureTableHigh, $this->naming->closureTableDepthName($keyId)),
				new ColumnReference($closureTableLow, $this->naming->closureTableDepthName($keyId)),
				new Constant(1)
			])),
		];
		
		$condition = new BinaryOperation(
			new Equal(),
			new Tuple([
				new ColumnReference($closureTableLow, $this->naming->closureParentColumnName($keyId)),
				new ColumnReference($closureTableHigh, $this->naming->closureChildColumnName($keyId)),
			]),
			new Tuple([
				$idParam,
				$parentParam,
			]),
		);

		if($this->schemaDef->isKeyScoped($keyId)) {
			$columns[] = $this->naming->nodeOwnScopeColumnName($keyId);
			$projections[] = new Projection($scopeParam);
		}

		$select = new Select($projections, [$closureTableLow, $closureTableHigh], [], $condition);
	
		return new Insert($closureTableName, $columns, $select);
	}

	public function getUpdateforReorderNode($keyId, $idParam, $orderParam) {
// UPDATE sorted_tree AS t
// SET priority=inn.new_order
// FROM (
// SELECT 
// o._id AS id, 
// o._normalized_order AS prev_order,
// CASE
// WHEN s._id = o._id 
// THEN 9
// WHEN 9 < s._normalized_order AND (o._normalized_order BETWEEN 9 AND s._normalized_order-1)
// THEN o._normalized_order + 1
// WHEN 9 > s._normalized_order AND  (o._normalized_order BETWEEN s._normalized_order+1 AND 9)
// THEN o._normalized_order - 1
// ELSE o._normalized_order
// END AS new_order
// FROM _sorted_tree_normalized_order s
// LEFT JOIN _sorted_tree_normalized_order o 
// ON (s._parent, s._scope) IS (o._parent, o._scope)
// WHERE s._id = 11
// ) AS inn
// WHERE inn.id = t.id AND inn.prev_order <> inn.new_order
		$table = new TableReference($this->naming->nodeTableName($keyId));
		$normalizedViewSelf = new TableReference($this->naming->normalizedOrderViewName($keyId), new Identifier('normalized_self'));
		$normalizedViewSiblings = new TableReference($this->naming->normalizedOrderViewName($keyId), new Identifier('normalized_siblings'));

		$normalizedOrderSelf = new ColumnReference($normalizedViewSelf, $this->naming->normalizedOrderNormalizedColumnName($keyId));
		$normalizedOrderSibling = new ColumnReference($normalizedViewSiblings, $this->naming->normalizedOrderNormalizedColumnName($keyId));

		$idSelf = new ColumnReference($normalizedViewSelf, $this->naming->normalizedOrderIdColumnName($keyId));
		$idSibling = new ColumnReference($normalizedViewSiblings, $this->naming->normalizedOrderIdColumnName($keyId));

		$innerId = new Projection(new ColumnReference($normalizedViewSiblings, $this->naming->normalizedOrderIdColumnName($keyId)), new Identifier('innerid'));
		$innerNew = new Projection(
			new Cases(
				new BinaryOperation(new Equal(), $idSelf, $idSibling), 
				$orderParam,

				// 9 < s._normalized_order AND (o._normalized_order BETWEEN 9 AND s._normalized_order-1)

				// 9 < s._normalized_order AND 
				// 9 <= o._normalized_order  AND 
				// o._normalized_order <= s._normalized_order-1)
				new AssociativeOperation(new Conjunction(), [
					new BinaryOperation(new LessThan(), $orderParam, $normalizedOrderSelf),
					new BinaryOperation(new LessThanEqual(), $orderParam, $normalizedOrderSibling,
					),
					new BinaryOperation(new LessThanEqual(), $normalizedOrderSibling, new BinaryOperation(new Subtraction(), $normalizedOrderSelf, new Constant(1)))
				]), 
				new BinaryOperation(new Addition(), $normalizedOrderSibling, new Constant(1)),


				// 9 > s._normalized_order AND  (o._normalized_order BETWEEN s._normalized_order+1 AND 9)

				// 9 > s._normalized_order AND  
				// ( s._normalized_order+1 <= o._normalized_order AND 
				//o._normalized_order <= 9)
				new AssociativeOperation(new Conjunction(), [
					new BinaryOperation(new GreaterThan(), $orderParam, $normalizedOrderSelf),
					new BinaryOperation(new LessThanEqual(),
						new BinaryOperation(new Addition(), $normalizedOrderSelf, new Constant(1)), $normalizedOrderSibling
					),
					new BinaryOperation(new LessThanEqual(), $normalizedOrderSibling, $orderParam)
				]),
				new BinaryOperation(new Subtraction(), $normalizedOrderSibling, new Constant(1)),

				$normalizedOrderSibling
			)
		, new Identifier('innernew'));
		
		$innerOld = new Projection($normalizedOrderSibling, new Identifier('innerold'));

		return new Update($table, [
			new Setter(new ColumnReference($table, $this->naming->orderColumnName($keyId)), new Projected($innerNew))
		], new BinaryOperation(
			new Conjunction(),
			new BinaryOperation(
				new Equal(),
				new Projected($innerId),
				new ColumnReference($table, $this->naming->nodeTablePKName($keyId))
			),
			new BinaryOperation(
				new NotEqual(),
				new Projected($innerNew),
				new Projected($innerOld)
			)
		), new Select([
			$innerNew,
			$innerId,
			$innerOld,
		], [$normalizedViewSelf], [
			new Join($normalizedViewSiblings, new BinaryOperation(
				new Equal(true),
				new Tuple([
					new ColumnReference($normalizedViewSelf, $this->naming->normalizedOrderScopeColumnName($keyId)), 
					new ColumnReference($normalizedViewSelf, $this->naming->normalizedOrderParentColumnName($keyId))]),
				new Tuple([
					new ColumnReference($normalizedViewSiblings, $this->naming->normalizedOrderScopeColumnName($keyId)), 
					new ColumnReference($normalizedViewSiblings, $this->naming->normalizedOrderParentColumnName($keyId))])
			), 'LEFT')
		], new BinaryOperation(new Equal(), $idSelf, $idParam)));
	}


	public function getCommandForRepairKey(string $keyId) {
		$result = [];

		if($this->schemaDef->isKeyReflexive($keyId)) {
			$result['invalid'] = $this->getDeleteForClosureRepair($keyId);
			$result['missing'] = $this->getInsertForClosureRepair($keyId);
		}

		if($this->schemaDef->isKeyOrdered($keyId)) {
			$result['order'] = $this->getUpdateForOrderRepair($keyId);
		}

		return $result;
	}

	public function getRepairableKeys() {
		return array_filter($this->schemaDef->getAllKeyIdsTopological(), 
			fn($keyId) => $this->schemaDef->isKeyReflexive($keyId) || $this->schemaDef->isKeyOrdered($keyId)
		);
	}

	public function getDeleteForClosureRepair($keyId) {
		$closureTable = new TableReference($this->naming->closureTableName($keyId));
		$invalidView = new TableReference($this->naming->closureInvalidViewName($keyId));
		$invalidViewId = new ColumnReference($invalidView, $this->naming->closureInvalidIdColumn($keyId));

		$idColumn = new ColumnReference($closureTable, $this->naming->closureTablePkName($keyId));

		return new Delete($closureTable, 
			new ElementOf(
				$idColumn,
				new Select([new Projection($invalidViewId)], [$invalidView])
			)
		);
	}

	public function getInsertForClosureRepair($keyId) {
		$closureTableName = $this->naming->closureTableName($keyId);
		$missingView = new TableReference($this->naming->closureMissingViewName($keyId));

		$targetColumns = [
			$this->naming->closureTablePkName($keyId),
			$this->naming->closureParentColumnName($keyId),
			$this->naming->closureChildColumnName($keyId),
			$this->naming->closureTableDepthName($keyId),
		];

		$sourceColumns = [
			new Projection(new ColumnReference($missingView, $this->naming->closureMissingIdColumn($keyId))),
			new Projection(new ColumnReference($missingView, $this->naming->closureMissingParentColumn($keyId))),
			new Projection(new ColumnReference($missingView, $this->naming->closureMissingChildColumn($keyId))),
			new Projection(new ColumnReference($missingView, $this->naming->closureMissingDepthColumn($keyId))),
		];

		if($this->schemaDef->isKeyScoped($keyId)) {
			$targetColumns[] = $this->naming->nodeOwnScopeColumnName($keyId);
			$sourceColumns[] = new Projection(new ColumnReference($missingView, $this->naming->nodeOwnScopeColumnName($keyId)));
		}

		return new Insert(
			$closureTableName,
			$targetColumns,
			new Select($sourceColumns, [$missingView])
		);
	}

	public function getUpdateForOrderRepair($keyId) {
		$table = new TableReference($this->naming->nodeTableName($keyId));
		$orderView = new TableReference($this->naming->normalizedOrderViewName($keyId));
		$orderColumn = new ColumnReference($table, $this->naming->orderColumnName($keyId));
		$orderId = new ColumnReference($orderView, $this->naming->normalizedOrderIdColumnName($keyId));
		$storedOrder = new ColumnReference($orderView, $this->naming->normalizedOrderStoredColumnName($keyId));
		$normalizedOrder = new ColumnReference($orderView, $this->naming->normalizedOrderNormalizedColumnName($keyId));
		
		$innerNormalized = new Projection($normalizedOrder, new Identifier("normalized_order"));
		$innerId = new Projection($orderId, new Identifier("inner_id"));


		return new Update($table, [
				new Setter($orderColumn, new Projected($innerNormalized))
			],
			new BinaryOperation(
				new Equal(),
				new ColumnReference($table, $this->naming->nodeTablePKName($keyId)),
				new Projected($innerId)
			),
			new Select([$innerId, $innerNormalized], [$orderView], [], new BinaryOperation(
				new NotEqual(),
				$storedOrder,
				$normalizedOrder
			))
		);
	}

}