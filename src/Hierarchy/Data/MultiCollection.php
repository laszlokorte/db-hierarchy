<?php

namespace App\Hierarchy\Data;

class MultiCollection {
	public function __construct(
		private ?string $keyId, 
		private ?string $nodeId, 
		private array $groupedRows, 
		private ?string $scopeId = NULL, 
		private ?string $parentId = NULL
	) {
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

	public function getNodesFor($keyId) {
		if($keyId === $this->keyId) {
			return new NodeCollection(
				$this->keyId, $this->groupedRows[$keyId]??[], $this->scopeId, $this->nodeId
			);
		} else {
			return new NodeCollection(
				$keyId, $this->groupedRows[$keyId]??[], $this->nodeId, null
			);
		}
	}
}