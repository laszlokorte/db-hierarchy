<?php

namespace App\Hierarchy\Data;

class MultiTree {
	public function __construct(
		private ?string $keyId, 
		private ?string $nodeId, 
		private array $groupedRows, 
		private ?string $scopeId = NULL, 
		private ?string $parentId = NULL
	) {
	}

	public function getNodes($keyId, $scopeId = null, $parentId = null) {
		return $this->groupedRows[$keyId][($scopeId?:'-').'/'.($parentId?:'-')] ?? [];
	}
}