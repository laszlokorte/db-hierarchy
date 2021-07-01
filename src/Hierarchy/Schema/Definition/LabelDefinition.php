<?php

namespace App\Hierarchy\Schema\Definition;

class LabelDefinition {

	public function __construct(
		private $singular, 
		private $plural = NULL, 
		private $description = NULL, 
		private $icon = NULL, 
		private $color = 'black',
		private $none = 'Empty'
	) {
		$this->plural = $plural ?? $singular . 's';
	}

	public function getSingular() {
		return $this->singular;
	}

	public function getPlural() {
		return $this->plural;
	}

	public function getEmpty() {
		return $this->none;
	}

	public function getString($singular = true) {
		return $singular ? $this->singular : $this->plural;
	}

	public function getDescription() {
		return $this->description;
	}

	public function getIcon() {
		return $this->icon;
	}

	public function getColor() {
		return $this->color;
	}
}