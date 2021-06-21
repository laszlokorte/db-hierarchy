<?php

namespace App\Hierarchy\Data;

class Node {

	public function __construct(private string $keyId, private string $nodeId, private array $columns, private ?string $scopeId = NULL, private ?string $parentId = NULL, private ?string $order = NULL) {
	}

	public function getKey() {
		return $this->keyId;
	}

	public function getId() {
		return $this->nodeId;
	}

	public function getColumnValues() {
		return $this->columns;
	}

	public function getColumnValue($columnName) {
		return $this->columns[$columnName];
	}

}