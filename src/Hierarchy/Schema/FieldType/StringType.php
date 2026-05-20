<?php

namespace App\Hierarchy\Schema\FieldType;

use App\Hierarchy\Schema\Definition\ColumnDefinition;
use App\Hierarchy\Schema\Definition\StorageCoding;
use App\Hierarchy\Schema\Definition\StorageCodingType;

class StringType implements FieldTypeInterface
{

    public function __construct(private array $config = [])
    {
    }

    public function getColumns(string $fieldId, bool $required, array $fieldOptions) : array
    {
        return [
            new ColumnDefinition($fieldId, new StorageCoding(StorageCodingType::STRING), !$required, null),
        ];
    }

    public function fieldDataToColumnData(string $fieldId, array $fieldOptions, mixed $fieldData): array
    {
        return [$fieldData];
    }

    public function columnDataToFieldData(string $fieldId, array $fieldOptions, $columnData) : mixed
    {
        return $columnData[0] ?? '';
    }

    public function format(string $fieldId, array $fieldOptions, mixed $fieldData) : string
    {
        return $fieldData;
    }

    public function getSupportedFormats(string $fieldId, array $fieldOptions)    :array {
    }

    public function getTemplateName(string $fieldId, array $fieldOptions) : string
    {
        return 'string';
    }

    public function getDefaultOptions():array
    {
        return [];
    }
}
