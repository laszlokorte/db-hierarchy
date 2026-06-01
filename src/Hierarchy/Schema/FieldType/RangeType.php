<?php

namespace App\Hierarchy\Schema\FieldType;

class RangeType implements FieldTypeInterface
{
    public function __construct(private FieldTypeInterface $baseType)
    {
    }

    public function getColumns(string $fieldId, bool $required, array $fieldOptions): array
    {
        return array_merge([],
            $this->baseType->getColumns($fieldId.'_start', $required, $fieldOptions),
            $this->baseType->getColumns($fieldId.'_end', $required, $fieldOptions)
        );
    }

    public function fieldDataToColumnData(string $fieldId, array $fieldOptions, mixed $fieldData): array
    {
        return array_merge([],
            $this->baseType->fieldDataToColumnData($fieldId.'_start', $fieldOptions, $fieldData['start']),
            $this->baseType->fieldDataToColumnData($fieldId.'_end', $fieldOptions, $fieldData['end']),
        );
    }

    public function columnDataToFieldData(string $fieldId, array $fieldOptions, $columnData): mixed
    {
        return [
            'start' => $this->baseType->columnDataToFieldData($fieldId.'_start', $fieldOptions, $columnData),
            'end' => $this->baseType->columnDataToFieldData($fieldId.'_end', $fieldOptions, $columnData),
        ];
    }

    public function format(string $fieldId, array $fieldOptions, mixed $fieldData): string
    {
        return $fieldData['start'].'-'.$fieldData['end'];
    }

    public function getSupportedFormats(string $fieldId, array $fieldOptions): array
    {
    }

    public function getTemplateName(string $fieldId, array $fieldOptions): string
    {
        return 'range';
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
