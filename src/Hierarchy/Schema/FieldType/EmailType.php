<?php

namespace App\Hierarchy\Schema\FieldType;

use App\Hierarchy\Schema\Definition\ColumnDefinition;
use App\Hierarchy\Schema\Definition\StorageCoding;

class EmailType implements FieldTypeInterface {

	private $config;

	public function __construct($config = []) {
		$this->config = $config;
	}

	public function getColumns(string $fieldId, bool $required, array $fieldOptions) {
		return [
			new ColumnDefinition($fieldId, new StorageCoding(StorageCoding::TEXT), !$required, null)
		];
	}

	public function fieldDataToColumnData(string $fieldId, array $fieldOptions, mixed $fieldData) : array {
		return [$fieldData];
	}

	public function columnDataToFieldData(string $fieldId, array $fieldOptions, $columnData) {
		return $columnData[0];
	}

	public function format(string $fieldId, array $fieldOptions, mixed $fieldData) {
		return $fieldData;
	}

	public function getSupportedFormats(string $fieldId, array $fieldOptions) {

	}

	public function getTemplateName(string $fieldId, array $fieldOptions) {
		return 'email';
	}

	public function getDefaultOptions() {
		return [];
	}

}