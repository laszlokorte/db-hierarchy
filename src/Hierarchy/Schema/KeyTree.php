<?php

namespace App\Hierarchy\Schema;

class KeyTree {
	public function __construct(public Key $key, public $data, $scope = NULL, $parent = NULL) {

	}

	public function getNodes() {

	}

	public function getChildren() {

	}

	public function getChildNodes() {
		$result = [];

		$dataIndex = $this->scope.'/'.$this->parent;
		$rows = $this->data[$this->key->getId()][$scope.'/'.$parent];

		foreach ($rows as $row) {
			$result[] = new NodeTree($this->key, $data, $scope, $row['id']);
		}

		return $result;
	}
}