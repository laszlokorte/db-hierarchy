<?php

namespace App\Hierarchy\Schema\FieldType;

use App\Hierarchy\Schema\Definition\ColumnDefinition;
use App\Hierarchy\Schema\Definition\StorageCoding;

class BooleanType implements FieldTypeInterface {


	public function __construct() {
	}

	public function getColumns(string $fieldId, bool $required, array $fieldOptions) {
		return [
			new ColumnDefinition($fieldId, new StorageCoding(StorageCoding::BOOL), !$required, null)
		];
	}

	public function fieldDataToColumnData(string $fieldId, array $fieldOptions, mixed $fieldData) : array {
		switch($fieldData) {
			case 'true': $casted = '1'; break;
			case 'false': $casted = '0'; break;
			default: $casted = null; break;
		}
		return [$casted];
	}

	public function columnDataToFieldData(string $fieldId, array $fieldOptions, $columnData) {
		switch($columnData[0]) {
			case '0': return false;
			case '1': return true;
			default: return null;
		}
	}

	public function format(string $fieldId, array $fieldOptions, mixed $fieldData) {
		switch($fieldData) {
			case 'true': return 'true';
			case 'false': return 'false';
			default: return 'null';
		}
	}

	public function getSupportedFormats(string $fieldId, array $fieldOptions) {
	}

	public function getTemplateName(string $fieldId, array $fieldOptions) {
		return 'boolean';
	}

	public function getDefaultOptions() {
		return [];
	}

}