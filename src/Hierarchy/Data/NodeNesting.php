<?php

namespace App\Hierarchy\Data;

class NodeNesting {
	private ?string $keyId;
	private ?string $scopeId;
	private ?string $parentId;

	public function __construct(string $keyId, ?string $scopeId, ?string $parentId) {
		$this->keyId = $keyId;
		$this->scopeId = $scopeId;
		$this->parentId = $parentId;
	}

	public function isReflexiveNested() {
		return $this->parentId !== null;
	}

	public function __toString() {
		return sprintf('%s/%s', $this->scopeId?:'', $this->parentId?:'');
	}
}