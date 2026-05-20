<?php

namespace App\Hierarchy\Schema\Definition;

use App\Hierarchy\Schema\FieldType\FieldTypeInterface;

class SchemaDefinition
{
    /**
     * @param array<int,mixed> $keys
     * @param array<int,mixed> $fieldTypes
     */
    public function __construct(
        private LabelDefinition $label,
        private array $keys,
        private array $fieldTypes,
    ) {
    }

    public function getSchemaLabel(): LabelDefinition
    {
        return $this->label;
    }

    public function getRootScopeKeyIds(): array
    {
        return array_filter(array_keys($this->keys), [$this, 'isKeyRoot']);
    }

    public function getScopedKeyIds(): array
    {
        return array_filter(array_keys($this->keys), [$this, 'isKeyScoped']);
    }

    public function getReflexiveKeyIds(): array
    {
        return array_filter(array_keys($this->keys), [$this, 'isKeyReflexive']);
    }

    public function getOrderedKeyIds(): array
    {
        return array_filter(array_keys($this->keys), [$this, 'isKeyOrdered']);
    }
    /**
     * @return int[]|string[]
     */
    public function getAllKeyIds() : array
    {
        return array_keys($this->keys);
    }

    public function getAllKeyIdsTopological(): array
    {
        $keys = [];
        $roots = [null];

        while (!empty($roots)) {
            $r = array_pop($roots);
            $keys[] = $r;
            $newRoots = [];

            foreach ($this->keys as $key => $definition) {
                if ($definition->getScopeKeyId() === $r) {
                    $newRoots[] = $key;
                }
            }
            usort($newRoots,
                fn ($keyA, $keyB) => count($this->getReferencingKeys($keyA)) -
                    count($this->getReferencingKeys($keyB))
            );
            $roots = array_merge($roots, $newRoots);
        }

        array_shift($keys);

        if (count($keys) < count($this->keys)) {
            throw new \Exception('cyclic hierarchy');
        }

        return $keys;
    }

    public function validate(): void
    {
        $this->getAllKeyIdsTopological();
    }
    /**
     * @param mixed $keyId
     * @param mixed $singletons
     * @param mixed $skipAtoms
     */
    public function getKeyIdsScopedInside($keyId, $singletons = true, $skipAtoms = false): array
    {
        return array_filter(array_keys($this->keys),
            fn ($k) => $this->isKeyScopedInside($k, $keyId)
            && ($singletons || !$this->isKeySingleton($k))
            && (!$skipAtoms || !$this->isKeyAtomic($k)));
    }
    /**
     * @param mixed $keyId
     * @param mixed $singletons
     */
    public function getKeyIdsScopedInsideAndReflexiveSelf($keyId, $singletons = true): array
    {
        return array_filter(array_keys($this->keys),
            fn ($k) => $this->isKeyScopedInsideOrReflexiveSelf($k, $keyId)
             && ($singletons || !$this->isKeySingleton($k)));
    }

    public function getKeyNestings(): array
    {
        $keys = array_keys($this->keys);

        return array_combine(
            $keys,
            array_map(fn ($k) => $this->getKeyIdsScopedInsideAndReflexiveSelf($k), $keys)
        );
    }
    /**
     * @param mixed $keyId
     * @param mixed $includeSelf
     */
    public function getKeyScopePath($keyId, $includeSelf = false): array
    {
        $scopeIds = [];

        $currentKey = $includeSelf ? $keyId : $this->getKeyScopeId($keyId);

        while ($currentKey) {
            $scopeIds[] = $currentKey;
            $currentKey = $this->getKeyScopeId($currentKey);
        }

        return array_reverse($scopeIds);
    }
    /**
     * @param mixed $keyId
     */
    public function keyExists($keyId): bool
    {
        return array_key_exists($keyId, $this->keys);
    }
    /**
     * @param mixed $keyId
     */
    public function getKeyLabel($keyId) : LabelDefinition
    {
        return $this->keys[$keyId]->getLabel();
    }
    /**
     * @param mixed $keyId
     */
    public function isKeyOrdered($keyId) : bool
    {
        return $this->keys[$keyId]->isOrdered();
    }
    /**
     * @param mixed $keyId
     */
    public function getKeyOrderColumn($keyId): ColumnDefinition
    {
        return new ColumnDefinition($this->keys[$keyId]->getOrderColumnName(), new StorageCoding(StorageCodingType::INTEGER), false, 0);
    }
    /**
     * @param mixed $keyId
     */
    public function getKeyOrderColumnName($keyId) : string
    {
        return $this->keys[$keyId]->getOrderColumnName();
    }
    /**
     * @param mixed $keyId
     */
    public function getKeySingletonColumnName($keyId) : string
    {
        return $this->keys[$keyId]->getOrderColumnName();
    }
    /**
     * @param mixed $keyId
     */
    public function getKeyOrderDirection($keyId) : string
    {
        return $this->keys[$keyId]->getOrderDirection();
    }
    /**
     * @param mixed $keyId
     */
    public function isKeyScoped($keyId) : bool
    {
        return $this->keys[$keyId]->isScoped();
    }
    /**
     * @param mixed $keyId
     */
    public function isKeyScopeIsolating($keyId) : bool
    {
        return $this->keys[$keyId]->isScopeIsolating();
    }
    /**
     * @param mixed $keyId
     */
    public function isKeyRoot($keyId): bool
    {
        return !$this->isKeyScoped($keyId);
    }
    /**
     * @param mixed $keyId
     * @param mixed $scopeKeyId
     */
    public function isKeyScopedInside(string $keyId, string $scopeKeyId) : bool
    {
        return $this->keys[$keyId]->isScopedInside($scopeKeyId);
    }
    /**
     * @param mixed $keyId
     * @param mixed $scopeKeyId
     */
    public function isKeyScopedInsideOrReflexiveSelf(string $keyId, string $scopeKeyId) : bool
    {
        if ($keyId === $scopeKeyId) {
            return $this->isKeyReflexive($keyId);
        }

        return $this->keys[$keyId]->isScopedInside($scopeKeyId);
    }
    /**
     * @param mixed $keyId
     */
    public function isKeySingleton($keyId) : bool
    {
        return $this->keys[$keyId]->isSingleton();
    }
    /**
     * @param mixed $keyId
     */
    public function getKeyScopeId($keyId) : ?string
    {
        return $this->keys[$keyId]->getScopeKeyId();
    }
    /**
     * @param mixed $keyId
     */
    public function isKeyReflexive($keyId) : bool
    {
        return $this->keys[$keyId]->isReflexive();
    }
    /**
     * @param mixed $keyId
     */
    public function isKeyNested($keyId): bool
    {
        return $this->keys[$keyId]->isReflexive() || $this->keys[$keyId]->isScoped();
    }
    /**
     * @param mixed $keyId
     */
    public function getKeyReflexivityTableName($keyId) : string
    {
        return $this->keys[$keyId]->getReflexivityTableName();
    }
    /**
     * @param mixed $keyId
     */
    public function isKeyAtomic($keyId) : bool
    {
        return $this->keys[$keyId]->isAtomic();
    }
    /**
     * @param mixed $keyId
     */
    public function getKeyReflexivityParentColumn($keyId) : ColumnDefinition
    {
        return $this->getKeyIdentityColumn($keyId)->deriveSameWithName($this->keys[$keyId]->getReflexivityParentColumnName());
    }
    /**
     * @param mixed $keyId
     */
    public function getKeyReflexivityChildColumn($keyId) : ColumnDefinition
    {
        return $this->getKeyIdentityColumn($keyId)->deriveSameWithName($this->keys[$keyId]->getReflexivityChildColumnName());
    }
    /**
     * @param mixed $fieldTypeId
     */
    public function fieldTypeExists($fieldTypeId): bool
    {
        return array_key_exists($fieldTypeId, $this->fieldTypes);
    }
    /**
     * @param mixed $keyId
     * @param mixed $fieldId
     */
    public function keyFieldExists($keyId, $fieldId): bool
    {
        return $this->keyExists($keyId) && $this->keys[$keyId]->fieldExists($fieldId);
    }
    /**
     * @param mixed $keyId
     * @param mixed $all

     * @return array
     * */
    public function getKeyFieldIds(string $keyId, bool $all = true) : array
    {
        if ($all) {
            return $this->keys[$keyId]->getFieldIds();
        }

        return array_filter(
            $this->keys[$keyId]->getFieldIds(),
            fn ($fieldId) => $this->keys[$keyId]->isFieldVisibleInCollection($fieldId)
        );
    }
    /**
     * @param mixed $keyId
     * @param mixed $fieldId
     */
    public function getKeyFieldLabel($keyId, $fieldId) : LabelDefinition
    {
        return $this->keys[$keyId]->getFieldLabel($fieldId);
    }
    /**
     * @param mixed $keyId
     * @param mixed $fieldId
     */
    public function isKeyFieldRequired($keyId, $fieldId) :bool
    {
        return $this->keys[$keyId]->isFieldRequired($fieldId);
    }
    /**
     * @param mixed $keyId
     * @param mixed $fieldId
     */
    public function isKeyFieldUnique($keyId, $fieldId) :bool
    {
        return $this->keys[$keyId]->isFieldUnique($fieldId);
    }
    /**
     * @param mixed $keyId
     * @param mixed $fieldId
     */
    public function getKeyFieldTypeId($keyId, $fieldId) : string
    {
        return $this->keys[$keyId]->getFieldTypeId($fieldId);
    }
    /**
     * @param mixed $keyId
     * @param mixed $fieldId
     */
    public function getKeyFieldOptions($keyId, $fieldId) : array
    {
        $fieldType = $this->getKeyFieldType($keyId, $fieldId);

        return array_merge($fieldType->getDefaultOptions(), $this->keys[$keyId]->getFieldOptions($fieldId));
    }
    /**
     * @param mixed $keyId
     * @param mixed $fieldId
     * @param mixed $optionId
     */
    public function getKeyFieldOption($keyId, $fieldId, $optionId) : mixed
    {
        return $this->getKeyFieldOptions($keyId, $fieldId)[$optionId] ?? null;
    }
    /**
     * @param mixed $keyId
     * @param mixed $fieldId
     */
    public function getKeyFieldColumns($keyId, $fieldId) : array
    {
        $fieldType = $this->getKeyFieldType($keyId, $fieldId);
        $fieldOptions = $this->getKeyFieldOptions($keyId, $fieldId);
        $required = $this->isKeyFieldRequired($keyId, $fieldId);

        return $fieldType->getColumns($fieldId, $required, $fieldOptions);
    }
    /**
     * @param mixed $keyId
     * @param mixed $fieldId
     * @param mixed $fieldData
     */
    public function convertKeyFieldDataToColumnData($keyId, $fieldId, $fieldData) : mixed
    {
        $fieldType = $this->getKeyFieldType($keyId, $fieldId);
        $fieldOptions = $this->getKeyFieldOptions($keyId, $fieldId);

        return $fieldType->fieldDataToColumnData($fieldId, $fieldOptions, $fieldData);
    }
    /**
     * @param mixed $keyId
     * @param mixed $fieldId
     * @return mixed
     */
    public function getKeyFieldType($keyId, $fieldId) :  FieldTypeInterface
    {
        $fieldTypeId = $this->getKeyFieldTypeId($keyId, $fieldId);

        return $this->fieldTypes[$fieldTypeId];
    }
    /**
     * @param mixed $keyId
     */
    public function getKeyIdentityColumnName($keyId) : string
    {
        return $this->keys[$keyId]->getIdColumnName();
    }
    /**
     * @param mixed $keyId
     */
    public function getKeyIdentityColumnType($keyId) : string
    {
        return $this->keys[$keyId]->getIdColumnType();
    }
    /**
     * @param mixed $keyId
     */
    public function getKeyIdentityColumn($keyId) : ColumnDefinition
    {
        return $this->keys[$keyId]->getIdColumn();
    }
    /**
     * @param mixed $keyId
     */
    public function getKeyScopeColumnName($keyId) : string
    {
        return $this->keys[$keyId]->getScopeColumnName();
    }
    /**
     * @param mixed $keyId
     */
    public function getKeyScopeColumnType($keyId) : string
    {
        $scopeId = $this->getKeyScopeId($keyId);

        return $this->keys[$scopeId]->getIdColumnType();
    }
    /**
     * @param mixed $keyId
     */
    public function getKeyScopeColumn($keyId) : ColumnDefinition
    {
        return $this->getKeyIdentityColumn($this->getKeyScopeId($keyId))
            ->deriveSameWithName($this->getKeyScopeColumnName($keyId));
    }
    /**
     * @param mixed $keyId
     */
    public function getKeyIsolationColumn($keyId) : ColumnDefinition
    {
        return $this->getKeyIdentityColumn($this->getKeyScopeId($keyId))
            ->deriveSameWithName('_iso_'.$this->getKeyScopeColumnName($keyId));
    }
    /**
     * @param mixed $keyId
     * @param mixed $includeSelf
     * @return array|mixed[]
     */
    public function getKeyIsolations($keyId, $includeSelf = false): array
    {
        $scopeIds = [];

        if ($includeSelf && $this->isKeyScoped($keyId) && $this->isKeyScopeIsolating($keyId)) {
            $scopeIds[] = $keyId;
        }

        $currentKey = $this->getKeyScopeId($keyId);

        while ($currentKey) {
            if ($this->isKeyScoped($currentKey) && $this->isKeyScopeIsolating($currentKey)) {
                $scopeIds[] = $currentKey;
            }
            $currentKey = $this->getKeyScopeId($currentKey);
        }

        return $scopeIds;
    }
    /**
     * @param mixed $keyId
     * @return ColumnDefinition[]
     */
    public function getKeyIsolationColumns(string $keyId): array
    {
        return array_map(
            fn ($iso) => $this->getKeyScopeColumn($iso),
            $this->getKeyIsolations($keyId)
        );
    }
    /**
     * @param mixed $keyA
     * @param mixed $keyB
     */
    public function getCommonIsolation(string $keyA, string $keyB): ?array
    {
        $isoA = array_reverse($this->getKeyIsolations($keyA, true));
        $isoB = array_reverse($this->getKeyIsolations($keyB, true));
        $commonLength = min(count($isoA), count($isoB));

        $result = null;

        for ($i = 0; $i < $commonLength; ++$i) {
            if ($this->getKeyScopeId($isoA[$i]) === $this->getKeyScopeId($isoB[$i])) {
                $result = [$isoA[$i], $isoB[$i]];
            } else {
                break;
            }
        }

        return $result;
    }
    /**
     * @param mixed $keyId
     */
    public function getKeyTableName(string $keyId) : string
    {
        return $this->keys[$keyId]->getTableName();
    }
    /**
     * @param mixed $targetKey
     * @param mixed $sourceKey
     * @param mixed $fieldId
     * @return bool
     */
    public function isKeyReferencedByField(string $targetKey, string $sourceKey, string $fieldId) : bool
    {
        foreach ($this->getKeyFieldColumns($sourceKey, $fieldId) as $column) {
            if ($column->isReferencing($targetKey)) {
                return true;
            }
        }

        return false;
    }
    /**
     * @param mixed $targetKey
     * @param mixed $sourceKey
     */
    public function isKeyReferencedBy($targetKey, $sourceKey): bool
    {
        foreach ($this->getKeyFieldIds($sourceKey) as $fieldId) {
            if ($this->isKeyReferencedByField($targetKey, $sourceKey, $fieldId)) {
                return true;
            }
        }
        return false;
    }
    /**
     * @param mixed $targetKey
     */
    public function getReferencingKeys($targetKey): array
    {
        return array_filter($this->getAllKeyIds(), fn ($keyId) => $this->isKeyReferencedBy($targetKey, $keyId));
    }
    /**
     * @param mixed $targetKey
     * @param mixed $sourceKey
     */
    public function getReferencingKeyColumns($targetKey, $sourceKey): array
    {
        $result = [];
        foreach ($this->getKeyFieldIds($sourceKey) as $fieldId) {
            foreach ($this->getKeyFieldColumns($sourceKey, $fieldId) as $column) {
                if ($column->isReferencing($targetKey)) {
                    $result[] = $column;
                }
            }
        }

        return $result;
    }
    /**
     * @param mixed $keyId
     */
    public function getKeySummary($keyId) : SummaryDefinition
    {
        return $this->keys[$keyId]->getSummary();
    }
    /**
     * @param mixed $keyId
     */
    public function getKeySummaryFieldIds($keyId) : array
    {
        return $this->getKeySummary($keyId)->getFieldIds();
    }
    /**
     * @param mixed $keyId
     * @return array<string,string>
     */
    public function getKeySummaryColumns(string $keyId): array
    {
        return array_merge([], ...array_map(
            fn ($fieldId) => $this->getKeyFieldColumns($keyId, $fieldId),
            $this->getKeySummary($keyId)->getFieldIds())
        );
    }
    /**
     * @param mixed $keyId
     */
    public function isKeyLeaf(string $keyId): bool
    {
        return empty($this->getReferencingKeys($keyId)) && empty($this->getKeyIdsScopedInside($keyId));
    }
}
