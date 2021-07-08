<?php

namespace App\Hierarchy\Storage\Relational\Service;

use App\Hierarchy\Storage\Relational\Naming;
use App\Hierarchy\Storage\Relational\ColumnCoder;
use App\Hierarchy\Schema\Definition\SchemaDefinition;

class RepairCommandBuilder  {

	public function __construct(private SchemaDefinition $schemaDef, private Naming $naming, private ColumnCoder $coder) {
	}

	// getDiagnosableKeys
	// getDiagnosisQueriesForKey
	// getRepairableKeys
	// getCommandForRepairKey

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