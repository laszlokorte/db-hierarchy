<?php

namespace App\Hierarchy;


class Definition {
	public $structure = [
		'site' => ['parent' => null, 'reflexive' => true, 'order' => false, 'fields' => ['slug'], 'generator' => true],
		'route' => ['parent' => 'site', 'reflexive' => true, 'order' => 'priority', 'fields' => ['slug'], 'generator' => true],
		'content' => ['parent' => 'route', 'reflexive' => true, 'order' => 'priority', 'fields' => ['slug'], 'generator' => true],
		'menu' => ['parent' => null, 'reflexive' => false, 'order' => false, 'fields' => ['slug'], 'generator' => false],
		'menu_item' => ['parent' => 'menu', 'reflexive' => true, 'order' => 'priority', 'fields' => ['slug'], 'generator' => true],
		'resource_directory' => ['parent' => null, 'reflexive' => true, 'order' => false, 'fields' => ['slug'], 'generator' => false],
		'resource' => ['parent' => 'resource_directory', 'reflexive' => false, 'order' => false, 'fields' => ['slug','content_type'], 'generator' => false],
		'example_parent' => ['parent' => null, 'reflexive' => false, 'order' => false, 'fields' => ['slug'], 'generator' => false],
		'example_child' => ['parent' => 'example_parent', 'reflexive' => false, 'order' => false, 'fields' => ['slug'], 'generator' => false],
		'sorted_parent' => ['parent' => null, 'reflexive' => false, 'order' => 'priority', 'fields' => ['slug'], 'generator' => false],
		'sorted_child' => ['parent' => 'sorted_parent', 'reflexive' => false, 'order' => 'priority', 'fields' => ['slug'], 'generator' => false],
		'sorted_tree' => ['parent' => null, 'reflexive' => true, 'order' => 'priority', 'fields' => ['slug'], 'generator' => false],
	];

	public function getRootKeys() {
		return array_keys(array_filter($this->structure, function($def) {
			return $def['parent'] === null;
		}));
	}

	public function getChildKeys($parentKey) {
		return array_keys(array_filter($this->structure, function($def, $key) use ($parentKey) {
			return $def['parent'] === $parentKey || $key === $parentKey && $def['reflexive'];
		}, ARRAY_FILTER_USE_BOTH));
	}

	public function getAllFields() {
		return array_map(function($def) {
			return $def['fields'];
		}, $this->structure);
	}

	public function getChildFields($parentKey) {
		$keys = $this->getChildKeys($parentKey);
		return array_combine($keys, 
			array_map(function($key) {
				return $this->structure[$key]['fields'];
			}, $keys)
		);
	}

	public function getFields($key) {
		return $this->structure[$key]['fields'];
	}

	public function getParentKey($key) {
		return $this->structure[$key]['parent'];
	}

	public function isMoveable($key) {
		return $this->isScoped($key) || $this->isReflexive($key);
	}

	public function isScoped($key) {
		return $this->structure[$key]['parent'] !== NULL;
	}

	public function isReflexive($key) {
		return $this->structure[$key]['reflexive'] === TRUE;
	}

	public function topoSorted() {
		$keys = [];
		$roots = [null];

		while(!empty($roots)) {
			$r = array_pop($roots);
			$keys[] = $r;

			foreach($this->structure AS $key => $config) {
				if($config['parent'] === $r) {
					$roots[] = $key;
				}
			}
		}

		if(count($keys) < count($this->structure)) {
			throw new Exception("cyclic hierarchy");
		}

		array_shift($keys);

		return $keys;
	}
}