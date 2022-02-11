<?php

namespace App\Hierarchy\Data;

class MultiTree {
	public function __construct(
		private array $groupedRows
	) {
	}

	public function getNodes(string $keyId, $scopeId = null, $parentId = null) {
		return array_map(fn($data) => 
			new Node($keyId, $data['_id'], $data, $scopeId, $parentId, $data['_order']),
			$this->groupedRows[$keyId][($scopeId?:'-').'/'.($parentId?:'-')] ?? []
		);
	}

	public function hasNodes(string $keyId, $scopeId = null, $parentId = null) {
		return !empty($this->groupedRows[$keyId][($scopeId?:'-').'/'.($parentId?:'-')]);
	}

	public function hasAnyNodes(string $keyId, $scopeId = null, $parentId = null) {
		return !empty($this->groupedRows[$keyId]);
	}

	public function isEmpty() {
		return empty($this->groupedRows);
	}

	public function getScopes(string $keyId) {
		return array_unique(array_map(fn($k) => explode('/',$k,2)[0], array_keys($this->groupedRows[$keyId])));
	}
}