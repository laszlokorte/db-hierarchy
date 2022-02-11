<?php

namespace App\Hierarchy\Data;

use App\Hierarchy\Changeset\Update;

class Node {

	public function __construct(private string $keyId, private string $nodeId, private array $columns, private ?string $scopeId = NULL, private ?string $parentId = NULL, private ?int $order = null) {
	}

	public function getKey() {
		return $this->keyId;
	}

	public function getId() {
		return $this->nodeId;
	}

	public function getScope() {
		return $this->scopeId;
	}

	public function getOrder() {
		return $this->order;
	}

	public function getParent() {
		return $this->parentId;
	}

	public function hasScope() {
		return !empty($this->scopeId);
	}

	public function hasParent() {
		return !empty($this->parentId);
	}

	public function getColumnValues() {
		return $this->columns;
	}

	public function getColumnValue($columnName) {
		return $this->columns[$columnName];
	}

	public function pathArgs() {
		return ['keyId' => $this->keyId, 'nodeId' => $this->nodeId];
	}

	public function newUpdate() {
		return new Update(
			$this->keyId, 
			$this->nodeId,
			[],
			$this->columns,
			[]
		);
	}

	public function __toString() {
		return $this->scopeId . '/' . $this->nodeId;
	}

	public function getLocator() {
		return $this->scopeId . '/' . $this->nodeId;
	}

}