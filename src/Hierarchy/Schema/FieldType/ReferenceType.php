<?php

namespace App\Hierarchy\Schema\FieldType;

use App\Hierarchy\Schema\Definition\ColumnDefinition;
use App\Hierarchy\Schema\Definition\ReferenceCoding;

class ReferenceType implements FieldTypeInterface {


	public function __construct() {
	}

	public function getColumns(string $fieldId, bool $required, array $fieldOptions) {
		if($required) {
			$cascade = $fieldOptions['cascade']??false ? ReferenceCoding::FOLLOW : ReferenceCoding::RESTRICT; 
		} else {
			$cascade = $fieldOptions['cascade']??false ? ReferenceCoding::CLEAR : ReferenceCoding::RESTRICT;
		}

		return [
			new ColumnDefinition($fieldId . '_ref', new ReferenceCoding($fieldOptions['target'], $cascade), !$required, null)
		];
	}

	public function fieldDataToColumnData(string $fieldId, array $fieldOptions, mixed $fieldData) : array {
		return [$fieldData['id']??null?:null];
	}

	public function columnDataToFieldData(string $fieldId, array $fieldOptions, $columnData) {
		return [
			'id' => $columnData[0],
			'key' => $fieldOptions['target'],
		];
	}

	public function format(string $fieldId, array $fieldOptions, mixed $fieldData) {
		return $fieldOptions['target'] . '-' . $fieldData['id'];
	}

	public function getSupportedFormats(string $fieldId, array $fieldOptions) {

	}

	public function getTemplateName(string $fieldId, array $fieldOptions) {
		return 'reference';
	}

	public function getDefaultOptions() {
		return [];
	}

}