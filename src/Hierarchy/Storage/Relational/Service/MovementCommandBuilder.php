<?php

namespace App\Hierarchy\Storage\Relational\Service;

use App\Hierarchy\Storage\Relational\Naming;
use App\Hierarchy\Storage\Relational\ColumnCoder;
use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Storage\Relational\Algebra\Value\Parameter;
use App\Hierarchy\Storage\Relational\Algebra\Value\Constant;
use App\Hierarchy\Storage\Relational\Algebra\TableReference;
use App\Hierarchy\Storage\Relational\Algebra\Projection;
use App\Hierarchy\Storage\Relational\Algebra\Identifier;
use App\Hierarchy\Storage\Relational\Algebra\Select;
use App\Hierarchy\Storage\Relational\Algebra\Join;
use App\Hierarchy\Storage\Relational\Algebra\Order;
use App\Hierarchy\Storage\Relational\Algebra\Value\AssociativeOperation;
use App\Hierarchy\Storage\Relational\Algebra\Value\BinaryOperation;
use App\Hierarchy\Storage\Relational\Algebra\Value\FunctionApplication;
use App\Hierarchy\Storage\Relational\Algebra\Value\ColumnReference;
use App\Hierarchy\Storage\Relational\Algebra\Operator\String\Concat;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Function\Coalesce;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Comparison\Equal;

class MovementCommandBuilder  {

	public function __construct(private SchemaDefinition $schemaDef, private Naming $naming, private ColumnCoder $coder) {
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

	public function getSelectForMoveTargetValid(string $keyId, Parameter $idParam, Parameter $parentParam) {
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
				new Tuple([
					$this->coder->wrapPrimaryKeyParameter($keyId, $idParam), 
					$this->coder->wrapParentParameter($keyId, $parentParam)
				])
			)
		);
	}

	public function getUpdateForMoveOwnScope(string $keyId, Parameter $idParam, Parameter $scopeParam) {
		$table = new TableReference($this->naming->nodeTableName($keyId));
		$scopeTable = new TableReference($this->naming->scopeTablename($keyId), new Identifier('scope'));

		$projections = [
			new Projection(new ColumnReference($scopeTable, $this->naming->scopeTablePKName($keyId)), new Identifier('scope_id')),
		];

		$setters = [
			new Setter(
				new ColumnReference($table, $this->naming->nodeOwnScopeColumnName($keyId)),
				new Projected($projections[0])
			)
		];

		$isolations = $this->schemaDef->getKeyIsolations($keyId);

		foreach ($isolations as $i => $isolation) {
			$pr = new Projection(
				new ColumnReference($scopeTable, 
					$isolation === $this->schemaDef->getKeyScopeId($keyId)?
					$this->naming->nodeOwnScopeColumnName($isolation)
					: $this->naming->nodeOwnIsolationColumnName($isolation)
				), new Identifier('iso_'.$i)
			);
			$projections[] = $pr;
			$setters[] = new Setter(
				new ColumnReference($table, $this->naming->nodeOwnIsolationColumnName($isolation)),
				new Projected($pr)
			);
		}
		
		return new Update(
			$table, $setters,
			new BinaryOperation(
				new Equal(), 
				new ColumnReference($table, $this->naming->nodeTablePKName($keyId)),
				$this->coder->wrapPrimaryKeyParameter($keyId, $idParam)
			),
			new Select($projections, [$scopeTable], [], new BinaryOperation(
				new Equal(), 
				new ColumnReference($scopeTable, $this->naming->scopeTablePKName($keyId)),
				$this->coder->wrapScopeParameter($keyId, $scopeParam)
			))
		);
	}

	public function getUpdateForMoveClosureScope(string $keyId, Parameter $idParam, Parameter $scopeParam) {
		$table = new TableReference($this->naming->nodeTableName($keyId));
		$closureTable = new TableReference($this->naming->closureTableName($keyId));
		// UPDATE %s SET %s_id = :parentId WHERE id = :id
		return new Update(
			$table, [
				new Setter(
					new ColumnReference($table, $this->naming->nodeOwnScopeColumnName($keyId)),
					$this->coder->wrapScopeParameter($keyId, $scopeParam)
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
					$this->coder->wrapPrimaryKeyParameter($keyId, $idParam)
				))
			)
		);
	}

	public function getUpdateForMoveClosureParents(string $keyId, Parameter $idParam, Parameter $scopeParam) {
		$table = new TableReference($this->naming->nodeTableName($keyId));
		$closureTable = new TableReference($this->naming->closureTableName($keyId));
		return new Update(
			$closureTable, [
				new Setter(
					new ColumnReference($closureTable, $this->naming->nodeOwnScopeColumnName($keyId)),
					$this->coder->wrapScopeParameter($keyId, $scopeParam)
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
					$this->coder->wrapPrimaryKeyParameter($keyId, $idParam)
				))
			)
		);
	}

	public function getDeleteForMoveClosureOldParents(string $keyId, Parameter $idParam) {
		
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
					$this->coder->wrapPrimaryKeyParameter($keyId, $idParam),
				),
				new BinaryOperation(
					new LessThan(),
					new ColumnReference($closureTableOk, $this->naming->closureTableDepthName($keyId)),
					new ColumnReference($closureTableBad, $this->naming->closureTableDepthName($keyId)),
				)
			))
		));
	}

	public function getInsertForMoveClosureParents(string $keyId, Parameter $idParam, Parameter $scopeParam, Parameter $parentParam) {

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
				$this->coder->wrapPrimaryKeyParameter($keyId, $idParam),
				$this->coder->wrapParentParameter($keyId, $parentParam),
			]),
		);

		if($this->schemaDef->isKeyScoped($keyId)) {
			$columns[] = $this->naming->nodeOwnScopeColumnName($keyId);
			$projections[] = new Projection($this->coder->wrapScopeParameter($keyId, $scopeParam));
		}

		$select = new Select($projections, [$closureTableLow, $closureTableHigh], [], $condition);
	
		return new Insert($closureTableName, $columns, $select);
	}

}