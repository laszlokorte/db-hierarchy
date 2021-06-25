<?php

namespace App\Hierarchy\Data;

class NodeField {
	public function __construct(private string $keyId, private string $nodeId, private string $fieldId, private mixed $columns) {
	}

	public function getColumnValue($colName) {
		return $this->columns[$colName];
	}
}