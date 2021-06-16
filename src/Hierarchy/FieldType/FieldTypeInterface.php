<?php

namespace App\Hierarchy\FieldType;

interface FieldTypeInterface {

	public function getColumns($fieldName);

	public function fieldDataToColumnData($fieldName, $fieldData);

	public function columnDataToFieldData($fieldName, $columnData);

	public function format($fieldData);

	public function getSupportedFormats();

}