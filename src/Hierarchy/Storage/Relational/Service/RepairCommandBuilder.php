<?php

namespace App\Hierarchy\Storage\Relational\Service;

use App\Hierarchy\Storage\Relational\Service\InstallationCommandBuilder;

use App\Hierarchy\Storage\Relational\Naming;
use App\Hierarchy\Storage\Relational\ColumnCoder;
use App\Hierarchy\Schema\Definition\SchemaDefinition;

use App\Hierarchy\Storage\Relational\Algebra\TableReference;
use App\Hierarchy\Storage\Relational\Algebra\Value\BinaryOperation;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Comparison\NotEqual;
use App\Hierarchy\Storage\Relational\Algebra\Value\ColumnReference;
use App\Hierarchy\Storage\Relational\Algebra\Value\ElementOf;
use App\Hierarchy\Storage\Relational\Algebra\Select;
use App\Hierarchy\Storage\Relational\Algebra\Projection;
use App\Hierarchy\Storage\Relational\Algebra\Identifier;
use App\Hierarchy\Storage\Relational\Algebra\Update;
use App\Hierarchy\Storage\Relational\Algebra\Delete;
use App\Hierarchy\Storage\Relational\Algebra\Insert;
use App\Hierarchy\Storage\Relational\Algebra\Setter;
use App\Hierarchy\Storage\Relational\Algebra\Value\Projected;
use App\Hierarchy\Storage\Relational\Algebra\Operator\Comparison\Equal;

use App\Hierarchy\Schema\Definition\StorageCoding;

class RepairCommandBuilder  {

	public function __construct(private SchemaDefinition $schemaDef, private Naming $naming, private ColumnCoder $coder) {
	}

	// getRepairableKeys
	// getCommandForRepairKey



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



	public function getDeleteForClosureRepair(string $keyId) {
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

	public function getInsertForClosureRepair(string $keyId) {
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

	public function getUpdateForOrderRepair(string $keyId) {
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

	public function getSelectForFindKeyClosureMissings($keyId) {
		$missingView = new TableReference($this->naming->closureMissingViewName($keyId));
		return new Select([
			new Projection(
				$this->coder->decodeColumnType(
					InstallationCommandBuilder::CLOSURE_TABLE_PK_TYPE,
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
					InstallationCommandBuilder::CLOSURE_TABLE_DEPTH_TYPE,
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
					InstallationCommandBuilder::CLOSURE_TABLE_PK_TYPE,
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
					InstallationCommandBuilder::CLOSURE_TABLE_DEPTH_TYPE,
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
					StorageCodingType::INTEGER,
					new ColumnReference($orderView, $this->naming->normalizedOrderStoredColumnName($keyId))
				),
				$this->naming->normalizedOrderStoredColumnName($keyId)
			),
			new Projection(
				$this->coder->decodeColumnType(
					StorageCodingType::INTEGER,
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
}