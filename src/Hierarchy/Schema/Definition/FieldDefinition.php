<?php

namespace App\Hierarchy\Schema\Definition;

class FieldDefinition {
	private $typeId;
	private $required = false;
	private $unique = false;
	private $label = NULL;
	private $options;

	public function __construct($label, $typeId, $required = FALSE, $unique = FALSE, $options = []) {
		$this->label = $label;
		$this->typeId = $typeId;
		$this->required = $required;
		$this->unique = $unique;
		$this->options = $options;
	}

	public function getLabel() {
		return $this->label;
	}

	public function isRequired() {
		return $this->required;
	}

	public function isUnique() {
		return $this->unique;
	}

	public function getOptions() {
		return $this->options;
	}

	public function getOption($optionId) {
		return $this->options[$optionId] ?? null;
	}

	public function getTypeId() {
		return $this->typeId;
	}
}