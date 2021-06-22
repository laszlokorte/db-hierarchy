<?php

namespace App\Hierarchy\Data;

class NodePath {
	public function __construct(private string $keyId, private array $nodeIds) {

	}

	public function getKey() {
		return $this->keyId;
	}

	public function getNodeIdsBottomUp() {
		return $this->nodeIds;
	}

	public function getNodeIdsTopDown() {
		return array_reverse($this->nodeIds);
	}

	public function isEmpty() {
		return empty($this->nodeIds);
	}

	public function lastId() {
		return $this->nodeIds[count($this->nodeIds) - 1]??null;
	}
}