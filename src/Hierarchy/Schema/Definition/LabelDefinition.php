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
}