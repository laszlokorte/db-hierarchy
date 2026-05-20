<?php

namespace App\Hierarchy\Schema;

use App\Hierarchy\Data\Node;
use App\Hierarchy\Data\NodeField;
use App\Hierarchy\Schema\Definition\LabelDefinition;
use App\Hierarchy\Schema\Definition\SchemaDefinition;

class Field
{
    public function __construct(
        private SchemaDefinition $def,
        private string $keyId,
        private string $fieldId,
    ) {
    }

    public function getId(): string
    {
        return $this->fieldId;
    }

    public function getKey(): Key
    {
        return new Key($this->def, $this->keyId);
    }

    public function getType(): string
    {
        return $this->def->getKeyFieldTypeId($this->keyId, $this->fieldId);
    }

    public function getLabel(): LabelDefinition
    {
        return $this->def->getKeyFieldLabel($this->keyId, $this->fieldId);
    }

    public function isRequired(): bool
    {
        return $this->def->isKeyFieldRequired($this->keyId, $this->fieldId);
    }

    public function isUnique(): bool
    {
        return $this->def->isKeyFieldUnique($this->keyId, $this->fieldId);
    }

    public function getOption(string $name): mixed
    {
        return $this->def->getKeyFieldOption($this->keyId, $this->fieldId, $name);
    }

    public function readValueOf(Node $object): mixed
    {
        $type = $this->def->getKeyFieldType($this->keyId, $this->fieldId);
        $options = $this->def->getKeyFieldOptions($this->keyId, $this->fieldId);

        return $type->columnDataToFieldData($this->fieldId, $options, array_map(fn ($col) => $object->getColumnValue($col->getName()), $this->getColumns()));
    }

    public function readFormattedValueOf(Node $object): mixed
    {
        $type = $this->def->getKeyFieldType($this->keyId, $this->fieldId);
        $options = $this->def->getKeyFieldOptions($this->keyId, $this->fieldId);

        $fieldData = $type->columnDataToFieldData($this->fieldId, $options, array_map(fn ($col) => $object->getColumnValue($col->getName()), $this->getColumns()));

        return $type->format($this->fieldId, $options, $fieldData);
    }

    public function hasValue(Node $object): bool
    {
        $v = $this->readValueOf($object);

        return '' !== $v && null !== $v;
    }

    public function readObjectOf(NodeField $nodeField): mixed
    {
        $type = $this->def->getKeyFieldType($this->keyId, $this->fieldId);
        $options = $this->def->getKeyFieldOptions($this->keyId, $this->fieldId);

        return $type->columnDataToFieldData($this->fieldId, $options, array_map(fn ($col) => $nodeField->getColumnValue($col->getName()), $this->getColumns()));
    }

    // public function readValueOfCollection(NodeCollection $collection, $nodeId) {
    // 	return implode(';', array_map(fn($col) => $collection->getColumnValue($nodeId, $col->getName()), $this->getColumns()));
    // }

    private function getColumns(): array
    {
        $type = $this->def->getKeyFieldType($this->keyId, $this->fieldId);
        $options = $this->def->getKeyFieldOptions($this->keyId, $this->fieldId);
        $required = $this->def->isKeyFieldRequired($this->keyId, $this->fieldId);

        return $type->getColumns($this->fieldId, $required, $options);
    }

    public function getTemplateName(): string
    {
        $type = $this->def->getKeyFieldType($this->keyId, $this->fieldId);
        $options = $this->def->getKeyFieldOptions($this->keyId, $this->fieldId);

        return $type->getTemplateName($this->fieldId, $options);
    }
}
