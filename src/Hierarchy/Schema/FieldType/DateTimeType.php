<?php

namespace App\Hierarchy\Schema\FieldType;

use App\Hierarchy\Schema\Definition\ColumnDefinition;
use App\Hierarchy\Schema\Definition\StorageCoding;

class DateTimeType implements FieldTypeInterface {


	public function __construct() {
	}

	public function getColumns(string $fieldId, bool $required, array $fieldOptions) {
		return [];
	}

	public function fieldDataToColumnData(string $fieldId, array $fieldOptions, mixed $fieldData) : array {
		return [
			new ColumnDefinition($fieldId, new StorageCoding(StorageCoding::DATETIME), !$required, null)
		];
	}

	public function columnDataToFieldData(string $fieldId, array $fieldOptions, $columnData) {
		return null;
	}

	public function format(string $fieldId, array $fieldOptions, mixed $fieldData) {

	}

	public function getSupportedFormats(string $fieldId, array $fieldOptions) {

	}

	public function getTemplateName(string $fieldId, array $fieldOptions) {
		return 'datetime';
	}

}