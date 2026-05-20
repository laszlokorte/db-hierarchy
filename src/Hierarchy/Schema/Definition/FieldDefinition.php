<?php

namespace App\Hierarchy\Schema\Definition;

class FieldDefinition
{
    private $typeId;
    private $required = false;
    private $unique = false;
    private $label;
    private $options;
    private $visibleInCollection;

    public function __construct($label, $typeId, $required = false, $unique = false, $options = [], $visibleInCollection = true)
    {
        $this->label = $label;
        $this->typeId = $typeId;
        $this->required = $required;
        $this->unique = $unique;
        $this->options = $options;
        $this->visibleInCollection = $visibleInCollection;
    }

    public function getLabel()
    {
        return $this->label;
    }

    public function isRequired()
    {
        return $this->required;
    }

    public function isUnique()
    {
        return $this->unique;
    }

    public function isVisibleInCollection()
    {
        return $this->visibleInCollection;
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function getTypeId()
    {
        return $this->typeId;
    }
}
