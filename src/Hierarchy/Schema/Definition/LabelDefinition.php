<?php

namespace App\Hierarchy\Schema\Definition;

class LabelDefinition {

	public function __construct(
		private $singular, 
		private $plural = NULL, 
		private $description = NULL, 
		private $icon = NULL
	) {
		$this->plural = $plural ?? $singular . 's';
	}

	public function getSingular() {
		return $this->singular;
	}

	public function getPlural() {
		return $this->plural;
	}

	public function getDescription() {
		return $this->description;
	}

	public function getIcon() {
		return $this->icon;
	}
}