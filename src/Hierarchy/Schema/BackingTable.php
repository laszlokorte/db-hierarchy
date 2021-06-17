<?php

namespace App\Hierarchy\Schema;

use App\Hierarchy\Schema\Definition\SchemaDefinition;

class BackingTable {
	public function __construct(
		private SchemaDefinition $def, 
		private string $keyId
	) {
	}

	public function getTableName() {
		return $this->def->getKeyTable($this->keyId);
	}

	public function getKeyId() {
		return $this->keyId;
	}

	public function getColumns() {
		return array_merge(
			[$this->getPrimaryKeyColumn()], 
			$this->getOrderColumns(),
			$this->getScopeColumns(),
			$this->getAllFieldsColumns(),
		);
	}

	public function getOrderColumns() {
		return array_map(fn($c) => $c['column'], $this->getOrdering());
	}

	public function isOrdered() {
		return $this->def->isKeyOrdered($this->keyId);
	}

	public function getOrdering() {
		if($this->isOrdered()) {
			return [
				[
					'column' => $this->def->getKeyOrderColumn($this->keyId),
					'direction' => $this->def->getKeyOrderDirection($this->keyId),
				]
			];
		}

		return [];
	}

	public function getScopeColumns() {
		if($this->def->isKeyScoped($this->keyId)) {
			return [$this->def->getKeyScopeColumn($this->keyId)];
		}

		return [];
	}

	public function getForeignKeys() {
		if($this->def->isKeyScoped($this->keyId)) {
			$scopeKeyId = $this->def->getKeyScopeId($this->keyId);

			$ownColumn = $this->def->getKeyScopeColumn($this->keyId);
			$targetColumn = $this->def->getKeyIdentityColumn($scopeKeyId);

			$targetTable = $this->def->getKeyTable($scopeKeyId);

			return [
				['table' => $targetTable, 'ownColumns' => [$ownColumn], 'targetColumns' => [$targetColumn]],
			];
		}

		return [];
	}

	public function getUniqueContraints() {
		$result = [];

		if($this->def->isKeyScoped($this->keyId) && $this->def->isKeyScopedUnique($this->keyId)) {
			$result[] = [$this->def->getKeyScopeColumn($this->keyId)];
		}

		$uniqueFieldsIds = array_filter(
			$this->def->getKeyFieldIds($this->keyId), 
			fn($fieldId) => $this->def->isKeyFieldUnique($this->keyId, $fieldId)
		);

		foreach ($uniqueFieldsIds as $ufid) {
			$result[] = $this->getFieldColumns($ufid);
		}

		return $result;
	}

	public function getPrimaryKeyColumn() {
		return $this->def->getKeyIdentityColumn($this->keyId);
	}

	public function getAllFieldsColumns() {
		return array_merge([], ...array_map([$this, 'getFieldColumns'], $this->def->getKeyFieldIds($this->keyId)));
	}

	public function getFieldColumns($fieldId) {
		$fieldType = $this->def->getKeyFieldType($this->keyId, $fieldId);
		$options = $this->def->getKeyFieldOptions($this->keyId, $fieldId);

		return $fieldType->getColumns($fieldId, $options);
	}

	public function hasTreeClosure() {
		return $this->def->isKeyReflexive($this->keyId);
	}

	public function getClosureTable() {
		return new ClosureTable($this->def, $this->keyId);
	}

	public function getNormalizers() {
		$result = [];

		if($this->hasTreeClosure()) {
			$result = array_merge($result, $this->getClosureTable()->getNormalizers());
		}

		if($this->isOrdered()) {
			$result[] = new OrderNormalizer($this->def, $this->keyId);
		}

		return $result;
	}
}