<?php

namespace App\Hierarchy\Data;

use RecursiveIterator;

class MultiTreeIterator implements RecursiveIterator {

	private MultiTree $tree;

	private $keyId;
	private $scopeId;
	private $parentId;
	private $depth;

	private $nodes;

	public function __construct(MultiTree $tree, $keyId, $scopeId, $parentId, $depth) {
		$this->tree = $tree;
		$this->keyId = $keyId;
		$this->scopeId = $scopeId;
		$this->parentId = $parentId;
		$this->depth = $depth;
	}

	public function getChildren() : ?RecursiveIterator {
		return array_map(function($rootKey) {
			if($this->keyId == $rootKey) {
				$scopeId = $this->scopeId;
				$parentId = current($this->nodes)->getId();
				return new self($this->tree, $rootKey, $scopeId, $parentId, $this->depth+1);
			} else {
				$scopeId = current($this->nodes)->getScope();
				$parentId = null;
				return new self($this->tree, $rootKey, $scopeId, $parentId, $this->depth+1);
			}
		}, $this->tree->getRootKeys());
	}

	public function hasChildren(): bool {
		return $this->tree->hasNodes($this->keyId, $this->scopeId, $this->parentId);
	}

	public function current() : mixed {
		return (object)[
			'node' => current($this->nodes),
			'depth' => $this->depth,
		];
	}

	public function key() : mixed {
		return key($this->nodes);
	}

	public function next() : void {
		next($this->nodes);
	}

	public function rewind() : void {
		$this->nodes = $this->tree->getNodes($this->keyId, $this->scopeId, $this->parentId);
	}

	public function valid() : bool {
		return !empty($this->nodes);
	}

}