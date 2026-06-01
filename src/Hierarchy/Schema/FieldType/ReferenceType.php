<?php

namespace App\Hierarchy\Schema\FieldType;

use App\Hierarchy\Schema\Definition\ColumnDefinition;
use App\Hierarchy\Schema\Definition\ReferenceCoding;
use App\Hierarchy\Schema\Definition\ReferenceCodingCascade;

class ReferenceType implements FieldTypeInterface
{
    public function __construct()
    {
    }

    public function getColumns(string $fieldId, bool $required, array $fieldOptions): array
    {
        if ($required) {
            $cascade = $fieldOptions['cascade'] ?? false ? ReferenceCodingCascade::FOLLOW : ReferenceCodingCascade::RESTRICT;
        } else {
            $cascade = $fieldOptions['cascade'] ?? false ? ReferenceCodingCascade::CLEAR : ReferenceCodingCascade::RESTRICT;
        }

        return [
            new ColumnDefinition($fieldId.'_ref', new ReferenceCoding($fieldOptions['target'], $cascade), !$required, null),
        ];
    }

    public function fieldDataToColumnData(string $fieldId, array $fieldOptions, mixed $fieldData): array
    {
        return [$fieldData ? $fieldData['id'] : null];
    }

    public function columnDataToFieldData(string $fieldId, array $fieldOptions, $columnData): mixed
    {
        return [
            'id' => $columnData[0],
            'key' => $fieldOptions['target'],
        ];
    }

    public function format(string $fieldId, array $fieldOptions, mixed $fieldData): string
    {
        return $fieldOptions['target'].'-'.$fieldData['id'];
    }

    public function getSupportedFormats(string $fieldId, array $fieldOptions): array
    {
    }

    public function getTemplateName(string $fieldId, array $fieldOptions): string
    {
        return 'reference';
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
