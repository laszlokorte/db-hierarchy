<?php

namespace App\Hierarchy\Schema\FieldType;

use App\Hierarchy\Schema\Definition\ColumnDefinition;
use App\Hierarchy\Schema\Definition\StorageCoding;

class FileType implements FieldTypeInterface {

	private $config;

	public function __construct($config = []) {
		$this->config = $config;
	}

	public function getColumns(string $fieldId, bool $required, array $fieldOptions) {
		return [
			new ColumnDefinition(sprintf('%s_size', $fieldId), new StorageCoding(StorageCoding::INTEGER), !$required, null),
			new ColumnDefinition(sprintf('%s_path', $fieldId), new StorageCoding(StorageCoding::TEXT), !$required, null),
			new ColumnDefinition(sprintf('%s_mime_type', $fieldId), new StorageCoding(StorageCoding::TEXT), !$required, null),
			new ColumnDefinition(sprintf('%s_name', $fieldId), new StorageCoding(StorageCoding::TEXT), !$required, null),
		];
	}

	public function fieldDataToColumnData(string $fieldId, array $fieldOptions, mixed $fieldData) : array {
		$split = explode(',', $fieldData, 4);

		return [
			$split[0]??null,
			$split[1]??null,
			$split[2]??null,
			$split[3]??null,
		];
	}

	public function columnDataToFieldData(string $fieldId, array $fieldOptions, $columnData) {
		if(empty($columnData[0])) {
			return null;
		}

		dump($columnData);

		return array_combine([
			'size',
			'path',
			'mime_type',
			'name',
		] , $columnData);
	}

	public function format(string $fieldId, array $fieldOptions, mixed $fieldData) {

	}

	public function getSupportedFormats(string $fieldId, array $fieldOptions) {

	}

	public function getTemplateName(string $fieldId, array $fieldOptions) {
		return 'file';
	}

}