<?php

namespace App\Hierarchy\Schema\FieldType;

use App\Hierarchy\Schema\Definition\ColumnDefinition;

class DateTimeType implements FieldTypeInterface {


	public function __construct() {
	}

	public function getColumns(string $fieldId, array $fieldOptions) {
		return [];
	}

	public function fieldDataToColumnData(string $fieldId, array $fieldOptions, mixed $fieldData) : array {
		return [];
	}

	public function columnDataToFieldData(string $fieldId, array $fieldOptions, $columnData) {
		return null;
	}

	public function format(string $fieldId, array $fieldOptions, mixed $fieldData) {

	}

	public function getSupportedFormats(string $fieldId, array $fieldOptions) {

	}

}