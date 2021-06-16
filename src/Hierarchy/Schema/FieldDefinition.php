<?php

namespace App\Hierarchy\Schema;

class FieldDefinition {
	private $type;
	private $required = false;
	private $unique = false;
	private $label = NULL;
	private $options;

	public function __construct($type) {
		$this->type = $type;
	}
}