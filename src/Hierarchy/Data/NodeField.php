<?php

namespace App\Hierarchy\Data;

class NodeField
{
    public function __construct(private string $keyId, private string $nodeId, private string $fieldId, private mixed $columns)
    {
    }

    public function getColumnValue(string $colName): mixed
    {
        return $this->columns[$colName];
    }
}
