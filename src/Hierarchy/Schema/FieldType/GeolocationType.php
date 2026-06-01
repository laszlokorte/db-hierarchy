<?php

namespace App\Hierarchy\Schema\FieldType;

use App\Hierarchy\Schema\Definition\ColumnDefinition;
use App\Hierarchy\Schema\Definition\StorageCoding;
use App\Hierarchy\Schema\Definition\StorageCodingType;

class GeolocationType implements FieldTypeInterface
{
    private $config;

    public function __construct($config = [])
    {
        $this->config = $config;
    }

    public function getColumns(string $fieldId, bool $required, array $fieldOptions): array
    {
        return [
            new ColumnDefinition($fieldId.'_longitude', new StorageCoding(StorageCodingType::STRING), !$required, null),
            new ColumnDefinition($fieldId.'_latitude', new StorageCoding(StorageCodingType::STRING), !$required, null),
        ];
    }

    public function fieldDataToColumnData(string $fieldId, array $fieldOptions, mixed $fieldData): array
    {
        return [$fieldData['lon'], $fieldData['lat']];
    }

    public function columnDataToFieldData(string $fieldId, array $fieldOptions, $columnData): mixed
    {
        return [
            'lon' => $columnData[0],
            'lat' => $columnData[1],
        ];
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
        return 'geolocation';
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
