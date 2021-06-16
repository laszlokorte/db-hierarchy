<?php

namespace App\Hierarchy\Schema;

class FieldDefinition {
	private $type;
	private $required = false;
	private $label = NULL;

	public function __construct($type) {
		$this->type = $type;
	}
}