<?php

namespace App\Hierarchy\Schema;

class NodeTree {
	public function __construct(public Key $key, public $data, $scope = NULL, $id = NULL) {

	}

	public function getNodes() {

	}

	public function getChildren() {

	}

	public function getChildKeys() {
		return array_map(fn($key) => new KeyTree($key, $this->data),$this->key->getNestedKeys());
	}
}