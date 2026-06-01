<?php

namespace App\Hierarchy\Schema\FieldType;

use App\Hierarchy\Schema\Definition\ColumnDefinition;
use App\Hierarchy\Schema\Definition\StorageCoding;
use App\Hierarchy\Schema\Definition\StorageCodingType;

class HashType implements FieldTypeInterface
{
    public function __construct()
    {
    }

    public function getColumns(string $fieldId, bool $required, array $fieldOptions): array
    {
        return [
            new ColumnDefinition($fieldId, new StorageCoding(StorageCodingType::BINARY), !$required, null),
        ];
    }

    public function fieldDataToColumnData(string $fieldId, array $fieldOptions, mixed $fieldData): array
    {
        return [password_hash($fieldData, PASSWORD_DEFAULT)];
    }

    public function columnDataToFieldData(string $fieldId, array $fieldOptions, $columnData): mixed
    {
        return 'secret';
    }

    public function format(string $fieldId, array $fieldOptions, mixed $fieldData): string
    {
        return 'secret';
    }

    public function getSupportedFormats(string $fieldId, array $fieldOptions): array
    {
    }

    public function getTemplateName(string $fieldId, array $fieldOptions): string
    {
        return 'hash';
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
