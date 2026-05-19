<?php

namespace App\Hierarchy\Schema\FieldType;

interface FieldTypeInterface {

	public function getColumns(string $fieldId, bool $required, array $fieldOptions);

	public function fieldDataToColumnData(string $fieldId, array $fieldOptions, mixed $fieldData) : array;

	public function columnDataToFieldData(string $fieldId, array $fieldOptions, $columnData);

	public function format(string $fieldId, array $fieldOptions, mixed $fieldData);

	public function getSupportedFormats(string $fieldId, array $fieldOptions);

	public function getTemplateName(string $fieldId, array $fieldOptions);

	public function getDefaultOptions();

}