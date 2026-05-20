<?php

namespace App\Hierarchy\Data;

class Diagnostic
{
    public function __construct(private string $keyId, private array $rows, private array $columns)
    {
    }

    public function getKeyId()
    {
        return $this->keyId;
    }

    public function getTypes()
    {
        return array_keys($this->rows);
    }

    public function getRows($type)
    {
        return $this->rows[$type];
    }

    public function getColumns(string $type): array
    {
        return $this->columns[$type];
    }

    public function isValid()
    {
        return array_reduce($this->getTypes(), fn ($acc, $type) => $acc && $this->validType($type), true);
    }

    public function validType($type)
    {
        return empty($this->rows[$type]);
    }
}
