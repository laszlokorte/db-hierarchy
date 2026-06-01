<?php

namespace App\Hierarchy\Schema\FieldType;

use App\Hierarchy\Schema\Definition\ColumnDefinition;
use App\Hierarchy\Schema\Definition\StorageCoding;
use App\Hierarchy\Schema\Definition\StorageCodingType;

class BooleanType implements FieldTypeInterface
{
    public function __construct()
    {
    }

    public function getColumns(string $fieldId, bool $required, array $fieldOptions): array
    {
        return [
            new ColumnDefinition($fieldId, new StorageCoding(StorageCodingType::BOOL), !$required, null),
        ];
    }

    public function fieldDataToColumnData(string $fieldId, array $fieldOptions, mixed $fieldData): array
    {
        switch ($fieldData) {
            case true: $casted = '1';
                break;
            case false: $casted = '0';
                break;
            default: $casted = null;
                break;
        }

        return [$casted];
    }

    public function columnDataToFieldData(string $fieldId, array $fieldOptions, $columnData): mixed
    {
        switch ($columnData[0]) {
            case '0': return false;
            case '1': return true;
            default: return null;
        }
    }

    public function format(string $fieldId, array $fieldOptions, mixed $fieldData): string
    {
        switch ($fieldData) {
            case true: return 'Yes';
            case false: return 'No';
            default: return 'null';
        }
    }

    public function getSupportedFormats(string $fieldId, array $fieldOptions): array
    {
    }

    public function getTemplateName(string $fieldId, array $fieldOptions): string
    {
        return 'boolean';
    }

    public function getDefaultOptions(): array
    {
        return [];
    }

    public function isIsolated(): bool
    {
        return false;
    }
}
