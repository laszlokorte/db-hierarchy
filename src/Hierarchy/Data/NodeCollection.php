<?php

namespace App\Hierarchy\Data;

class NodeCollection implements \Countable {
	public function __construct(private string $keyId, private array $rows, private ?string $scopeId = NULL, private ?string $parentId = NULL) {
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

	public function isScoped() {
		return $this->scopeId !== null;
	}

	public function getParent() {
		return $this->parentId;
	}

	public function getColumnValue($nodeId, $columnName) {
		return $this->rows[$nodeId][$columnName];
	}

	public function getOrder($nodeId) {
		return $this->rows[$nodeId]['_order'] ?? null;
	}


	public function getNodeScope($nodeId) {
		return $this->rows[$nodeId]['_scope'] ?? null;
	}

	public function getNodeNestId($nodeId) {
		return ($this->rows[$nodeId]['_scope']?:'-').'/'.($this->rows[$nodeId]['_parent']?:'-');
	}

	public function getNode($nodeId) {
		return new Node(
			$this->keyId, 
			$nodeId, 
			$this->rows[$nodeId], 
			$this->rows[$nodeId]['_scope']??$this->scopeId, 
			$this->rows[$nodeId]['_parent']??$this->parentId
		);
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