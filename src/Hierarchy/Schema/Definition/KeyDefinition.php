<?php

namespace App\Hierarchy\Schema\Definition;

class KeyDefinition
{
    private $templateString = '';

    /**
     * @param array<int,mixed> $fields
     */
    public function __construct(private StorageDefinition $storage, private LabelDefinition $label, private ?ScopeDefinition $scope, private ?ReflexivityDefinition $reflexivity, private ?OrderDefinition $order, private array $fields, private SummaryDefinition $summary)
    {
        if (array_diff($summary->getFieldIds(), array_keys($fields))) {
            throw new \Exception(sprintf('unknown fields in key summary: %s', implode(', ', array_diff($summary->getFieldIds(), array_keys($fields)))));
        }
    }

    public function fieldExists($fieldId): bool
    {
        return array_key_exists($fieldId, $this->fields);
    }

    /**
     * @return int[]|string[]
     */
    public function getFieldIds(): array
    {
        return array_keys($this->fields);
    }

    public function isOrdered(): bool
    {
        return null !== $this->order && !$this->order->isSingleton();
    }

    public function getOrderColumnName(): string
    {
        return $this->order->getColumnName();
    }

    public function getOrderDirection(): string
    {
        return $this->order->getDirection();
    }

    public function isScoped(): bool
    {
        return null !== $this->scope;
    }

    public function isScopeIsolating(): bool
    {
        return $this->scope->isIsolating();
    }

    public function isSingleton(): bool
    {
        return null !== $this->order && $this->order->isSingleton();
    }

    public function getScopeKeyId(): ?string
    {
        return $this->isScoped() ? $this->scope->getScopeKeyId() : null;
    }

    public function getScopeColumnName(): string
    {
        return $this->scope->getColumnName();
    }

    public function isScopedInside($keyId): bool
    {
        return $this->isScoped() && $this->scope->getScopeKeyId() === $keyId;
    }

    public function isReflexive(): bool
    {
        return null !== $this->reflexivity;
    }

    public function isAtomic(): bool
    {
        return count($this->fields) < 2;
    }

    public function getReflexivityTableName(): string
    {
        return $this->reflexivity->deriveTableName($this->getTableName());
    }

    public function getReflexivityParentColumnName(): string
    {
        return $this->reflexivity->getParentColumnName();
    }

    public function getReflexivityChildColumnName(): string
    {
        return $this->reflexivity->getChildColumnName();
    }

    public function getTableName(): string
    {
        return $this->storage->getTableName();
    }

    public function getIdColumnName(): string
    {
        return $this->storage->getIdColumnName();
    }

    public function getIdColumnType(): string
    {
        return $this->storage->getIdColumnType();
    }

    public function getIdColumn(): ColumnDefinition
    {
        return $this->storage->getIdColumn();
    }

    public function getLabel(): LabelDefinition
    {
        return $this->label;
    }

    public function getFieldLabel($fieldId): LabelDefinition
    {
        return $this->fields[$fieldId]->getLabel();
    }

    public function isFieldRequired($fieldId): bool
    {
        return $this->fields[$fieldId]->isRequired();
    }

    public function isFieldUnique($fieldId): bool
    {
        return $this->fields[$fieldId]->isUnique();
    }

    public function isFieldVisibleInCollection($fieldId): bool
    {
        return $this->fields[$fieldId]->isVisibleInCollection();
    }

    public function getFieldTypeId($fieldId): string
    {
        return $this->fields[$fieldId]->getTypeId();
    }

    public function getFieldOptions($fieldId): array
    {
        return $this->fields[$fieldId]->getOptions();
    }

    public function getSummary(): SummaryDefinition
    {
        return $this->summary ?? new SummaryDefinition();
    }
}
