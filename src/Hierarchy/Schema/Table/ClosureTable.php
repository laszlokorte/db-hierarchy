<?php

namespace App\Hierarchy\Schema\Table;

use App\Hierarchy\Schema\Definition\SchemaDefinition;

class ClosureTable {
	public function __construct(
		private SchemaDefinition $def, 
		private string $keyId
	) {
	}

	public function getTableName() {
		return $this->def->getKeyReflexivityTable($this->keyId);
	}

	public function getKeyId() {
		return $this->keyId;
	}

	public function getColumns() {
		return array_merge(
			[$this->getPrimaryKeyColumn()],
			$this->getScopeColumns(),
			[$this->getParentColumn(), $this->getChildColumn(),$this->getDepthColumn()]
		);
	}

	public function getForeignKeys() {
		$targetTable = $this->def->getKeyTableName($this->keyId);
		$targetColumn = $this->def->getKeyIdentityColumn($this->keyId);

		$result = [];

		$result[] = [
			'table' => $targetTable, 
			'ownColumns' => array_merge($this->getScopeColumns(), [$this->getChildColumn()]), 
			'targetColumns' => array_merge($this->getScopeColumns(), [$targetColumn]),
		];
		$result[] = [
			'table' => $targetTable, 
			'ownColumns' => array_merge($this->getScopeColumns(), [$this->getParentColumn()]), 
			'targetColumns' => array_merge($this->getScopeColumns(), [$targetColumn]),
		];

		return $result;
	}

	public function getUniqueContraints() {
		$result = [
			[$this->getChildColumn(), $this->getParentColumn()],
			[$this->getChildColumn(), $this->getDepthColumn()],
		];

		if($this->def->isKeyScoped($this->keyId) && $this->def->isKeyScopedUnique($this->keyId)) {
			$result[] = [$this->def->getKeyScopeColumn($this->keyId), $this->getDepthColumn()];
		}

		return $result;
	}

	public function getPrimaryKeyColumn() {
		return new TableColumn('id', 'INTEGER', false, null);
	}

	public function getScopeColumns() {
		if($this->def->isKeyScoped($this->keyId)) {
			return [$this->def->getKeyScopeColumn($this->keyId)];
		}

		return [];
	}

	public function getDepthColumn() {
		return new TableColumn('depth', 'INTEGER', false, null);
	}

	public function getParentColumn() {
		return $this->def->getKeyReflexivityParentColumn($this->keyId);
	}

	public function getChildColumn() {
		return $this->def->getKeyReflexivityChildColumn($this->keyId);
	}
}