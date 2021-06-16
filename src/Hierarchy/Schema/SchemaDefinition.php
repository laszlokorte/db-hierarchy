<?php

namespace App\Hierarchy\Schema;

class SchemaDefinition {
	private $keys;
	private $fieldTypes;
	
	private $label = NULL;

	public function __construct($keys, $fieldTypes) {
		$this->keys = $keys;
		$this->fieldTypes = $fieldTypes;
	}
}