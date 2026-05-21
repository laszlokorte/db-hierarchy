<?php

namespace App\Hierarchy\Schema\Definition;

class StorageDefinition
{
    public function __construct(
        private string $tableName,
        private string $pkColumn = 'id',
        private StorageCodingType $pkType = StorageCodingType::UUID,
    ) {
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function getIdColumnName(): string
    {
        return $this->pkColumn;
    }

    public function getIdColumnType(): StorageCodingType
    {
        return $this->pkType;
    }

    public function getIdColumn(): ColumnDefinition
    {
        switch ($this->pkType) {
            case StorageCodingType::SERIAL:
                return new ColumnDefinition($this->pkColumn, new StorageCoding(StorageCodingType::SERIAL), false, null);
            case StorageCodingType::UUID:
                return new ColumnDefinition($this->pkColumn, new StorageCoding(StorageCodingType::UUID), false, null);
            case StorageCodingType::INTEGER:
                return new ColumnDefinition($this->pkColumn, new StorageCoding(StorageCodingType::INTEGER), false, null);
        }
    }
}
