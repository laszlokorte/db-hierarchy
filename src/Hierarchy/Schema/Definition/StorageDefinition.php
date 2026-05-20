<?php

namespace App\Hierarchy\Schema\Definition;

class StorageDefinition
{
    public function __construct(
        private string $tableName,
        private string $pkColumn = 'id',
        private string $pkType = 'uuid',
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

    public function getIdColumnType(): string
    {
        return $this->pkType;
    }

    public function getIdColumn(): ColumnDefinition
    {
        switch ($this->pkType) {
            case 'serial':
                return new ColumnDefinition($this->pkColumn, new StorageCoding(StorageCodingType::SERIAL), false, null);
            case 'uuid':
                return new ColumnDefinition($this->pkColumn, new StorageCoding(StorageCodingType::UUID), false, null);
            case 'manual':
                return new ColumnDefinition($this->pkColumn, new StorageCoding(StorageCodingType::INTEGER), false, null);
        }
    }
}
