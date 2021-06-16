<?php

namespace App\Hierarchy;

class Tree {
	public function __construct(public $definition, public $data) {

	}

	public function printHierarchies() {
		foreach ($this->definition->structure as $key => $parent) {
			if($parent['parent']) {
				continue;
			}
			echo $key . ':' . PHP_EOL;
			$this->printHierarchy($key, '', '', 1);
			
		}
	}

	function printHierarchy($hierarchyKey, $scope, $parent, $depth = 0) {
		$items = $this->data[$hierarchyKey][$scope.'/'.$parent] ?? [];

		foreach ($items as $item) {
			for ($i=0; $i < $depth; $i++) { 
				echo "\t";
			}

			echo $hierarchyKey . '-' . $item['id'] . PHP_EOL;

			$this->printHierarchy($hierarchyKey, $scope, $item['id'], $depth + 1);

			foreach ($this->definition->structure as $key => $parent) {
				if($parent['parent'] !== $hierarchyKey) {
					continue;
				}
				$this->printHierarchy($key, $item['id'], '', $depth + 1);
			}
		}
	}

	public function walkHierarchies($cb) {
		foreach ($this->definition->structure as $key => $parent) {
			if($parent['parent']) {
				continue;
			}
			$this->walkHierarchy($key, '', '', 0, $cb);
		}
	}

	public function walkHierarchyOfType($hierarchyKey, $cb, $depth=0) {
		$items = $this->data[$hierarchyKey] ?? [];


		foreach ($items as $index => $item) {
			[$scope, $id] = explode('/', $index, 2);
			
			if($id) continue;

			$this->walkHierarchy($hierarchyKey, $scope, '', 0, $cb);
		}
	}

	private function walkHierarchy($hierarchyKey, $scope, $parent, $depth = 0, $cb = NULL) {
		$items = $this->data[$hierarchyKey][$scope.'/'.$parent] ?? [];

		foreach ($items as $item) {
			if(!$cb || FALSE === $cb($hierarchyKey, $item['id'], $depth, $scope)) {
				continue;
			}

			$this->walkHierarchy($hierarchyKey, $scope, $item['id'], $depth + 1, $cb);

			foreach ($this->definition->structure as $key => $parent) {
				if($parent['parent'] !== $hierarchyKey) {
					continue;
				}
				$this->walkHierarchy($key, $item['id'], '', $depth + 1, $cb);
			}
		}
	}

	public function getNodes() {
		$result = [];
		foreach ($this->definition->structure as $key => $parent) {
			if($parent['parent']) {
				continue;
			}

			$result[] = new TreeNode($this, $key, '', '', 0);
		}

		return $result;
	}
}