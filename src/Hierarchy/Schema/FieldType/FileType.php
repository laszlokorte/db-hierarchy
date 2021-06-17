<?php

namespace App\Hierarchy\Schema\FieldType;

use App\Hierarchy\Schema\Table\TableColumn;

class FileType implements FieldTypeInterface {

	private $config;

	public function __construct($config = []) {
		$this->config = $config;
	}

	public function getColumns(string $fieldId, array $fieldOptions) {
		return [
			new TableColumn(sprintf('%s_size', $fieldId), 'INTEGER', true, null),
			new TableColumn(sprintf('%s_path', $fieldId), 'TEXT', true, null),
			new TableColumn(sprintf('%s_mime_type', $fieldId), 'TEXT', true, null),
			new TableColumn(sprintf('%s_name', $fieldId), 'TEXT', true, null),
		];
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