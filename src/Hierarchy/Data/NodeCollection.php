<?php

namespace App\Hierarchy\Data;

class NodeCollection implements \Countable {
	public function __construct(private string $keyId, private array $rows, private ?string $scopeIds = NULL, private ?string $parentId = NULL, private ?array $orders = NULL) {
	}

	public function getKey() {
		return $this->keyId;
	}

	public function getIds() {
		return array_keys($this->rows);
	}

	public function getScope() {
		return $this->scopeId;
	}

	public function getParent() {
		return $this->parentId;
	}

	public function getColumnValue($nodeId, $columnName) {
		return $this->rows[$nodeId][$columnName];
	}

	public function pathArgs($nodeId) {
		return ['key' => $this->keyId, 'id' => $nodeId];
	}

	public function isEmpty() {
		return count($this->rows) === 0;
	}

	public function count() {
		return count($this->rows);
	}
}