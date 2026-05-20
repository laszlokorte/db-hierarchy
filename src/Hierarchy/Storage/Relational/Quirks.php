<?php

namespace App\Hierarchy\Storage\Relational;

class Quirks
{
    public function __construct(private bool $noDeferredForeignKeys, private bool $noAlteredForeignKeys)
    {
    }

    public function noDeferredFK()
    {
        return $this->noDeferredForeignKeys;
    }

    public function noAlteredFK()
    {
        return $this->noAlteredForeignKeys;
    }
}
