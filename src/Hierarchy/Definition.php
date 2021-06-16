<?php

namespace App\Hierarchy;


class Definition {
	public $structure = [
		'site' => ['parent' => null, 'reflexive' => true, 'order' => false, 'fields' => ['slug' => ['type' => 'text']], 'generator' => true],
		'route' => ['parent' => 'site', 'reflexive' => true, 'order' => 'priority', 'fields' => ['slug' => ['type' => 'text']], 'generator' => true],
		'content' => ['parent' => 'route', 'reflexive' => true, 'order' => 'priority', 'fields' => ['slug' => ['type' => 'text']], 'generator' => true],
		'menu' => ['parent' => null, 'reflexive' => false, 'order' => false, 'fields' => ['slug' => ['type' => 'text']], 'generator' => false],
		'menu_item' => ['parent' => 'menu', 'reflexive' => true, 'order' => 'priority', 'fields' => ['slug' => ['type' => 'text']], 'generator' => true],
		'resource_directory' => ['parent' => null, 'reflexive' => true, 'order' => false, 'fields' => ['slug' => ['type' => 'text']], 'generator' => false],
		'resource' => ['parent' => 'resource_directory', 'reflexive' => false, 'order' => false, 'fields' => ['slug' => ['type' => 'text'],'image' => ['type' => 'image']], 'generator' => false],
		'example_parent' => ['parent' => null, 'reflexive' => false, 'order' => false, 'fields' => ['slug' => ['type' => 'text']], 'generator' => false],
		'example_child' => ['parent' => 'example_parent', 'reflexive' => false, 'order' => false, 'fields' => ['slug' => ['type' => 'text']], 'generator' => false],
		'sorted_parent' => ['parent' => null, 'reflexive' => false, 'order' => 'priority', 'fields' => ['slug' => ['type' => 'text']], 'generator' => false],
		'sorted_child' => ['parent' => 'sorted_parent', 'reflexive' => false, 'order' => 'priority', 'fields' => ['slug' => ['type' => 'text']], 'generator' => false],
		'sorted_tree' => ['parent' => null, 'reflexive' => true, 'order' => 'priority', 'fields' => ['slug' => ['type' => 'text']], 'generator' => false],
	];

	public $fieldTypes;

	public function __construct() {
		$this->fieldTypes = [
			'boolean' => [
				'columns' => ['%s'],
				'writer' => function($fieldData) {
					return [$fieldData];
				},
				'reader' => function($column1) {
					return $column1;
				},			
			],
			'number' => [
				'columns' => ['%s'],
				'writer' => function($fieldData) {
					return [$fieldData];
				},
				'reader' => function($column1) {
					return $column1;
				},			
			],
			'date' => [
				'columns' => ['%s'],
				'writer' => function($fieldData) {
					return [$fieldData];
				},
				'reader' => function($column1) {
					return $column1;
				},			
			],
			'time' => [
				'columns' => ['%s'],
				'writer' => function($fieldData) {
					return [$fieldData];
				},
				'reader' => function($column1) {
					return $column1;
				},			
			],
			'datetime' => [
				'columns' => ['%s'],
				'writer' => function($fieldData) {
					return [$fieldData];
				},
				'reader' => function($column1) {
					return $column1;
				},			
			],
			'file' => [
				'columns' => ['%s'],
				'writer' => function($fieldData) {
					return [$fieldData];
				},
				'reader' => function($column1) {
					return $column1;
				},			
			],
			'image' => [
				'columns' => ['%s_path','%s_content_type','%s_width','%s_height'],
				'writer' => function($fieldData) {
					return ['x','y','z','w'];
				},
				'reader' => function($path, $contentType, $width, $height) {
					return [
						'path' => $path,
						'contentType' => $contentType,
						'width' => $width,
						'height' => $height,
					];
				},			
			],
			'text' => [
				'columns' => ['%s'],
				'writer' => function($fieldData) {
					return [$fieldData];
				},
				'reader' => function($column1) {
					return $column1;
				},			
			],
			'enum' => [
				'columns' => ['%s'],
				'writer' => function($fieldData) {
					return [$fieldData];
				},
				'reader' => function($column1) {
					return $column1;
				},			
			],
		];
	}

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
			return array_keys($def['fields']);
		}, $this->structure);
	}

	public function getChildFields($parentKey) {
		$keys = $this->getChildKeys($parentKey);
		return array_combine($keys, 
			array_map(function($key) {
				return array_keys($this->structure[$key]['fields']);
			}, $keys)
		);
	}

	public function getFields($key) {
		return array_keys($this->structure[$key]['fields']);
	}

	public function hasField($key, $field) {
		return isset($this->structure[$key]['fields'][$field]);
	}

	public function fieldDataToColumnData($key, $fieldData) {
		$result = [];

		foreach ($this->structure[$key]['fields'] as $fieldName => $options) {
			$type = $options['type'];
			$writer = $this->fieldTypes[$type]['writer'];

			$columnData = $writer($fieldData[$fieldName]);

			foreach ($this->fieldTypes[$type]['columns'] as $i => $columnTemplate) {
				$columnName = sprintf($columnTemplate, $fieldName);
				$result[$columnName] = $columnData[$i];
			}
		}

		return $result;
	}

	public function columnDataToFieldData($key, $columnData) {
		$result = [
			'id' => $columnData['id']??null,
			'_scope' => $columnData['_scope']??null,
		];

		foreach ($this->structure[$key]['fields'] as $fieldName => $options) {
			$result[$fieldName] = $this->columnDataToSingleFieldData($key, $fieldName, $columnData);
		}

		return $result;
	}

	public function columnDataToSingleFieldData($key, $fieldName, $columnData) {
		$options = $this->structure[$key]['fields'][$fieldName];
		$type = $options['type'];
		$reader = $this->fieldTypes[$type]['reader'];

		$collectedColumns = $this->getFieldColumns($key, $fieldName);

		return $reader(...array_map(fn($c) => $columnData[$c], $collectedColumns));
	}

	public function getColumns($key) {
		$result = [];

		foreach ($this->structure[$key]['fields'] as $fieldName => $options) {
			$type = $options['type'];
			foreach ($this->fieldTypes[$type]['columns'] as $columnTemplate) {
				$result[] = sprintf($columnTemplate, $fieldName);
			}
		}

		return $result;
	}

	public function getFieldColumns($key, $fieldName) {
		$result = [];
		$options = $this->structure[$key]['fields'][$fieldName];
		$type = $options['type'];

		foreach ($this->fieldTypes[$type]['columns'] as $columnTemplate) {
			$result[] = sprintf($columnTemplate, $fieldName);
		}

		return $result;
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