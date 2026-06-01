<?php

namespace App\Hierarchy\Schema\FieldType;

use App\Hierarchy\Schema\Definition\ColumnDefinition;
use App\Hierarchy\Schema\Definition\StorageCoding;
use App\Hierarchy\Schema\Definition\StorageCodingType;

class RepeatType implements FieldTypeInterface
{
    public function __construct(private FieldTypeInterface $baseType)
    {
    }

    public function getBaseType(): FieldTypeInterface
    {
        return $this->baseType;
    }

    public function getColumns(string $fieldId, bool $required, array $fieldOptions): array
    {
        return [
            new ColumnDefinition($fieldId, new StorageCoding(StorageCodingType::INTEGER), !$required, null),
        ];
    }

    public function fieldDataToColumnData(string $fieldId, array $fieldOptions, mixed $fieldData): array
    {
        return $this->baseType->fieldDataToColumnData($fieldId, $fieldOptions, $fieldData);
    }

    public function columnDataToFieldData(string $fieldId, array $fieldOptions, $columnData): mixed
    {
        return $this->baseType->columnDataToFieldData($fieldId, $fieldOptions, $columnData);
    }

    public function format(string $fieldId, array $fieldOptions, mixed $fieldData): string
    {
        return $this->baseType->format($fieldId, $fieldOptions, $fieldData);
    }

    public function getSupportedFormats(string $fieldId, array $fieldOptions): array
    {
        return $this->baseType->getSupportedFormats($fieldId, $fieldOptions);
    }

    public function getTemplateName(string $fieldId, array $fieldOptions): string
    {
        return 'repeat';
    }

    public function getDefaultOptions(): array
    {
        return [];
    }

    public function isIsolated(): bool
    {
        return true;
    }
}
