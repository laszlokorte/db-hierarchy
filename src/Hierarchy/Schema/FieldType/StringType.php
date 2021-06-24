<?php

namespace App\Hierarchy\Schema\FieldType;

use App\Hierarchy\Schema\Definition\ColumnDefinition;

class StringType implements FieldTypeInterface {

	private $config;

	public function __construct($config = []) {
		$this->config = $config;
	}

	public function getColumns(string $fieldId, array $fieldOptions) {
		return [new ColumnDefinition($fieldId, 'VARCHAR', true, null)];
	}

	public function fieldDataToColumnData(string $fieldId, array $fieldOptions, mixed $fieldData) : array {
		return [$fieldData];
	}

	public function columnDataToFieldData(string $fieldId, array $fieldOptions, $columnData) {
		return $columnData[0];
	}

	public function format(string $fieldId, array $fieldOptions, mixed $fieldData) {

	}

	public function getSupportedFormats(string $fieldId, array $fieldOptions) {

	}

}