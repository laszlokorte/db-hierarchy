<?php

namespace App\Hierarchy\Schema\FieldType;

use App\Hierarchy\Schema\Definition\ColumnDefinition;
use App\Hierarchy\Schema\Definition\StorageCoding;
use App\Hierarchy\Schema\Definition\StorageCodingType;

class DateType implements FieldTypeInterface {


	public function __construct() {
	}

	public function getColumns(string $fieldId, bool $required, array $fieldOptions) {
		return [
			new ColumnDefinition($fieldId, new StorageCoding(StorageCodingType::DATE), !$required, null)
		];
	}

	public function fieldDataToColumnData(string $fieldId, array $fieldOptions, mixed $fieldData) : array {
		return [$fieldData ? $fieldData->format('Y-m-d') : null];
	}

	public function columnDataToFieldData(string $fieldId, array $fieldOptions, $columnData) {
		return empty($columnData[0]) ? null : \DateTime::createFromFormat('Y-m-d', $columnData[0]);
	}

	public function format(string $fieldId, array $fieldOptions, mixed $fieldData) {
		return $fieldData;
	}

	public function getSupportedFormats(string $fieldId, array $fieldOptions) {

	}

	public function getTemplateName(string $fieldId, array $fieldOptions) {
		return 'date';
	}

	public function getDefaultOptions() {
		return [];
	}

}