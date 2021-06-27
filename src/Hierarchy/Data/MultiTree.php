<?php

namespace App\Hierarchy\Data;

class MultiTree {
	public function __construct(
		private array $rootKeyIds,
		private array $groupedRows
	) {
	}

	public function getNodes($keyId, $scopeId = null, $parentId = null) {
		return array_map(fn($data) => 
			new Node($keyId, $data['_id'], $data, $scopeId, $parentId, $data['_order']),
			$this->groupedRows[$keyId][($scopeId?:'-').'/'.($parentId?:'-')] ?? []
		);
	}

	public function hasNodes($keyId, $scopeId = null, $parentId = null) {
		return !empty($this->groupedRows[$keyId][($scopeId?:'-').'/'.($parentId?:'-')]);
	}

	public function getRootKeys() {
		return $this->rootKeyIds;
	}

	public function isEmpty() {
		return empty($this->rootKeyIds) || empty($this->groupedRows);
	}
}