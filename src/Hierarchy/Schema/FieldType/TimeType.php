<?php

namespace App\Hierarchy\Schema\FieldType;

use App\Hierarchy\Schema\Definition\ColumnDefinition;
use App\Hierarchy\Schema\Definition\StorageCoding;
use App\Hierarchy\Schema\Definition\StorageCodingType;

class TimeType implements FieldTypeInterface
{
    public function __construct()
    {
    }

    public function getColumns(string $fieldId, bool $required, array $fieldOptions): array
    {
        return [
            new ColumnDefinition($fieldId, new StorageCoding(StorageCodingType::TIME), !$required, null),
        ];
    }

    public function fieldDataToColumnData(string $fieldId, array $fieldOptions, mixed $fieldData): array
    {
        return [$fieldData ? $fieldData->format('H:i:s') : null];
    }

    public function columnDataToFieldData(string $fieldId, array $fieldOptions, $columnData): mixed
    {
        return empty($columnData[0]) ? null : \DateTime::createFromFormat('H:i:s', $columnData[0]);
    }

    public function format(string $fieldId, array $fieldOptions, mixed $fieldData): string
    {
        return $fieldData;
    }

    public function getSupportedFormats(string $fieldId, array $fieldOptions): array
    {
    }

    public function getTemplateName(string $fieldId, array $fieldOptions): string
    {
        return 'time';
    }

    public function getDefaultOptions(): array
    {
        return [];
    }
}
