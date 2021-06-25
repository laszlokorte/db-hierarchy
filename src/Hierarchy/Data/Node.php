<?php

namespace App\Hierarchy\Data;

class Node {

	public function __construct(private string $keyId, private string $nodeId, private array $columns, private ?string $scopeId = NULL, private ?string $parentId = NULL) {
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

	public function joinedColumnValues() {
		dump(array_filter($this->columns, fn($k) => str_starts_with($k, '_'), ARRAY_FILTER_USE_KEY));
		return implode(', ', array_filter(array_filter($this->columns, fn($k) => !str_starts_with($k, '_'), ARRAY_FILTER_USE_KEY)));
	}

	public function pathArgs() {
		return ['key' => $this->keyId, 'id' => $this->nodeId];
	}

}