<?php

namespace App\Hierarchy\Schema\FieldType;

interface FieldTypeInterface
{
    /**
     * @param array<int,mixed> $fieldOptions
     *
     * @return void
     */
    public function getColumns(string $fieldId, bool $required, array $fieldOptions): array;

    /**
     * @param array<int,mixed> $fieldOptions
     *
     * @return void
     */
    public function fieldDataToColumnData(string $fieldId, array $fieldOptions, mixed $fieldData): array;

    /**
     * @param array<int,mixed> $fieldOptions
     */
    public function columnDataToFieldData(string $fieldId, array $fieldOptions, mixed $columnData): mixed;

    /**
     * @param array<int,mixed> $fieldOptions
     */
    public function format(string $fieldId, array $fieldOptions, mixed $fieldData): string;

    /**
     * @param array<int,mixed> $fieldOptions
     *
     * @return void
     */
    public function getSupportedFormats(string $fieldId, array $fieldOptions): array;

    /**
     * @param array<int,mixed> $fieldOptions
     */
    public function getTemplateName(string $fieldId, array $fieldOptions): string;

    /**
     * @return void
     */
    public function getDefaultOptions(): array;

    public function isIsolated(): bool;
}
