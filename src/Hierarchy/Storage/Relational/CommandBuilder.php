<?php

namespace App\Hierarchy\Storage\Relational;

use App\Hierarchy\Schema\Definition\SchemaDefinition;

use App\Hierarchy\Storage\Relational\Algebra\TableReference;
use App\Hierarchy\Storage\Relational\Algebra\Identifier;
use App\Hierarchy\Storage\Relational\Algebra\Value\ColumnReference;
use App\Hierarchy\Storage\Relational\Algebra\Value\Constant;
use App\Hierarchy\Storage\Relational\Algebra\Value\UnaryOperation;
use App\Hierarchy\Storage\Relational\Algebra\Value\BinaryOperation;
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
use App\Hierarchy\Storage\Relational\Algebra\Operator\Logic\Conjunction;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Numeric\Addition;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Function\Coalesce;
use App\Hierarchy\Storage\Relational\Algebra\Aggregation\Maximum;
use App\Hierarchy\Storage\Relational\Algebra\Select;
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
			$fieldType = $this->schemaDef->getKeyFieldType($keyId, $fieldId);
			$fieldOptions = $this->schemaDef->getKeyFieldOptions($keyId, $fieldId);
			foreach($fieldType->getColumns($fieldId, $fieldOptions) AS $column) {
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
			$fieldType = $this->schemaDef->getKeyFieldType($keyId, $fieldId);
			$fieldOptions = $this->schemaDef->getKeyFieldOptions($keyId, $fieldId);
			foreach($fieldType->getColumns($fieldId, $fieldOptions) AS $column) {
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
		// UPDATE %s SET %s_id = :parentId WHERE id = :id
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
		$closureTable = new TableReference($this->naming->closureTableName($keyId));
		// DELETE FROM %s_closure WHERE child_id = :id AND depth > 0
		return new Delete($closureTable, new BinaryOperation(
			new Conjunction(),
			new BinaryOperation(
				new Equal(),
				new ColumnReference($closureTable, $this->naming->closureChildColumnName($keyId)),
				$idParam
			),
			new BinaryOperation(
				new GreaterThan(),
				new ColumnReference($closureTable, $this->naming->closureTableDepthName($keyId)),
				new Constant(0)
			)
		));
	}

	public function getInsertForMoveClosureParents($keyId, $idParam, $scopeParam, $parentParam) {
		$closureTable = $this->naming->closureTableName($keyId);

		// INSERT INTO %s_closure (%s_id, parent_id, child_id, depth) VALUES(:scope, :parent, :id, :depth)

		$columns = [
			$this->naming->closureParentColumnName($keyId),
			$this->naming->closureChildColumnName($keyId),
			$this->naming->closureTableDepthName($keyId)
		];
		$values = [
			$parentParam,
			$idParam,
			new Constant(1),
		];

		if($this->schemaDef->isKeyScoped($keyId)) {
			$columns[] = $this->naming->nodeOwnScopeColumnName($keyId);
			$values[] = $scopeParam;
		}
	
		return new Insert($closureTable, $columns, [$values]);
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