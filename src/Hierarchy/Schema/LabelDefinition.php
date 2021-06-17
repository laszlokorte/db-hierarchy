<?php

namespace App\Hierarchy\Schema;

class LabelDefinition {
	private $singular;
	private $plural;
	private $description;
	private $icon;

	public function __construct($singular, $plural, $description, $icon) {
		$this->singular = $singular;
		$this->plural = $plural;
		$this->description = $description;
		$this->icon = $icon;
	}
}