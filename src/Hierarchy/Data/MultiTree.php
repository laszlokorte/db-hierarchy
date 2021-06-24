<?php

namespace App\Hierarchy\Data;

class MultiTree {
	public function __construct(
		private array $rootKeyIds,
		private array $groupedRows
	) {
	}

	public function getNodes($keyId, $scopeId = null, $parentId = null) {
		return $this->groupedRows[$keyId][($scopeId?:'-').'/'.($parentId?:'-')] ?? [];
	}

	public function getRootKeys() {
		return $this->rootKeyIds;
	}
}