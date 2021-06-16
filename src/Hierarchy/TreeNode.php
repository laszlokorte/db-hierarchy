<?php

namespace App\Hierarchy;

class TreeNode {
	public function __construct($tree, $hierarchyKey, $scope, $parent, $depth = 0, $id = null) {
		$this->tree = $tree;
		$this->hierarchyKey = $hierarchyKey;
		$this->scope = $scope;
		$this->parent = $parent;
		$this->depth = $depth;
		$this->id = $id;
	}

	public function getChildren() {
		$items = $this->tree->data[$this->hierarchyKey][$this->scope.'/'.$this->parent] ?? [];
		$result = [];


		foreach ($items as $item) {
			$result[] = new TreeNode($this->tree, $this->hierarchyKey, $this->scope, $item['id'], $this->depth + 1, $item['id']);

			foreach ($this->tree->definition->structure as $key => $this->parent) {
				if($this->parent['parent'] !== $this->hierarchyKey) {
					continue;
				}
				$result[] = new TreeNode($this->tree, $key, $item['id'], '', $this->depth + 1, null);
			}
		}

		return $result;
	}
}