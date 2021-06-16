<?php

namespace App\Hierarchy\Schema;

class LabelDefinition {
	private $singular;
	private $plural;
	private $description;

	public function __construct($singular, $plural, $description) {
		$this->singular = $singular;
		$this->plural = $plural;
		$this->description = $description;
	}
}