<?php

namespace App\Hierarchy\Schema\FieldType;

class TextType implements FieldTypeInterface {

	private $config;

	public function __construct($config = []) {
		$this->config = $config;
	}

	public function getColumns(string $fieldId, array $fieldOptions) {
		return [$fieldId];
	}

	public function fieldDataToColumnData(string $fieldId, array $fieldOptions, mixed $fieldData) : array {

	}

	public function columnDataToFieldData(string $fieldId, array $fieldOptions, ...$columnData) {

	}

	public function format(string $fieldId, array $fieldOptions, mixed $fieldData) {

	}

	public function getSupportedFormats(string $fieldId, array $fieldOptions) {

	}

}