<?php

namespace App\Hierarchy\Schema\FieldType;

use App\Hierarchy\Schema\Definition\ColumnDefinition;
use App\Hierarchy\Schema\Definition\StorageCoding;
use App\Hierarchy\Schema\Definition\StorageCodingType;
use DateTime;

class DateTimeType implements FieldTypeInterface
{
    public function __construct()
    {
    }

    public function getColumns(string $fieldId, bool $required, array $fieldOptions): array
    {
        return [
            new ColumnDefinition($fieldId, new StorageCoding(StorageCodingType::DATETIME), !$required, null),
        ];
    }

    public function fieldDataToColumnData(string $fieldId, array $fieldOptions, mixed $fieldData): array
    {
        return [$fieldData ? $fieldData->format('Y-m-d H:i:s') : null];
    }

    public function columnDataToFieldData(string $fieldId, array $fieldOptions, $columnData) : mixed
    {
        return empty($columnData[0]) ? null : \DateTime::createFromFormat('Y-m-d H:i:s', $columnData[0]);
    }

    public function format(string $fieldId, array $fieldOptions, mixed $fieldData) : string
    {
        return $fieldData;
    }

    public function getSupportedFormats(string $fieldId, array $fieldOptions): array    {
    }

    public function getTemplateName(string $fieldId, array $fieldOptions) : string
    {
        return 'datetime';
    }

    public function getDefaultOptions(): array
    {
        return [];
    }
}
