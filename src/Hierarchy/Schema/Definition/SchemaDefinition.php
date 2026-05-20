<?php

namespace App\Hierarchy\Schema\Definition;

class SchemaDefinition
{
    public function __construct(
        private LabelDefinition $label,
        private array $keys,
        private array $fieldTypes,
    ) {
    }

    public function getSchemaLabel()
    {
        return $this->label;
    }

    public function getRootScopeKeyIds()
    {
        return array_filter(array_keys($this->keys), [$this, 'isKeyRoot']);
    }

    public function getScopedKeyIds()
    {
        return array_filter(array_keys($this->keys), [$this, 'isKeyScoped']);
    }

    public function getReflexiveKeyIds()
    {
        return array_filter(array_keys($this->keys), [$this, 'isKeyReflexive']);
    }

    public function getOrderedKeyIds()
    {
        return array_filter(array_keys($this->keys), [$this, 'isKeyOrdered']);
    }

    public function getAllKeyIds()
    {
        return array_keys($this->keys);
    }

    public function getAllKeyIdsTopological()
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

    public function validate()
    {
        $this->getAllKeyIdsTopological();
    }

    public function getKeyIdsScopedInside($keyId, $singletons = true, $skipAtoms = false)
    {
        return array_filter(array_keys($this->keys),
            fn ($k) => $this->isKeyScopedInside($k, $keyId)
            && ($singletons || !$this->isKeySingleton($k))
            && (!$skipAtoms || !$this->isKeyAtomic($k)));
    }

    public function getKeyIdsScopedInsideAndReflexiveSelf($keyId, $singletons = true)
    {
        return array_filter(array_keys($this->keys),
            fn ($k) => $this->isKeyScopedInsideOrReflexiveSelf($k, $keyId)
             && ($singletons || !$this->isKeySingleton($k)));
    }

    public function getKeyNestings()
    {
        $keys = array_keys($this->keys);

        return array_combine(
            $keys,
            array_map(fn ($k) => $this->getKeyIdsScopedInsideAndReflexiveSelf($k), $keys)
        );
    }

    public function getKeyScopePath($keyId, $includeSelf = false)
    {
        $scopeIds = [];

        $currentKey = $includeSelf ? $keyId : $this->getKeyScopeId($keyId);

        while ($currentKey) {
            $scopeIds[] = $currentKey;
            $currentKey = $this->getKeyScopeId($currentKey);
        }

        return array_reverse($scopeIds);
    }

    public function keyExists($keyId)
    {
        return array_key_exists($keyId, $this->keys);
    }

    public function getKeyLabel($keyId)
    {
        return $this->keys[$keyId]->getLabel();
    }

    public function isKeyOrdered($keyId)
    {
        return $this->keys[$keyId]->isOrdered();
    }

    public function getKeyOrderColumn($keyId)
    {
        return new ColumnDefinition($this->keys[$keyId]->getOrderColumnName(), new StorageCoding(StorageCodingType::INTEGER), false, 0);
    }

    public function getKeyOrderColumnName($keyId)
    {
        return $this->keys[$keyId]->getOrderColumnName();
    }

    public function getKeySingletonColumnName($keyId)
    {
        return $this->keys[$keyId]->getOrderColumnName();
    }

    public function getKeyOrderDirection($keyId)
    {
        return $this->keys[$keyId]->getOrderDirection();
    }

    public function isKeyScoped($keyId)
    {
        return $this->keys[$keyId]->isScoped();
    }

    public function isKeyScopeIsolating($keyId)
    {
        return $this->keys[$keyId]->isScopeIsolating();
    }

    public function isKeyRoot($keyId)
    {
        return !$this->isKeyScoped($keyId);
    }

    public function isKeyScopedInside($keyId, $scopeKeyId)
    {
        return $this->keys[$keyId]->isScopedInside($scopeKeyId);
    }

    public function isKeyScopedInsideOrReflexiveSelf($keyId, $scopeKeyId)
    {
        if ($keyId === $scopeKeyId) {
            return $this->isKeyReflexive($keyId);
        }

        return $this->keys[$keyId]->isScopedInside($scopeKeyId);
    }

    public function isKeySingleton($keyId)
    {
        return $this->keys[$keyId]->isSingleton();
    }

    public function getKeyScopeId($keyId)
    {
        return $this->keys[$keyId]->getScopeKeyId();
    }

    public function isKeyReflexive($keyId)
    {
        return $this->keys[$keyId]->isReflexive();
    }

    public function isKeyNested($keyId)
    {
        return $this->keys[$keyId]->isReflexive() || $this->keys[$keyId]->isScoped();
    }

    public function getKeyReflexivityTableName($keyId)
    {
        return $this->keys[$keyId]->getReflexivityTableName();
    }

    public function isKeyAtomic($keyId)
    {
        return $this->keys[$keyId]->isAtomic();
    }

    public function getKeyReflexivityParentColumn($keyId)
    {
        return $this->getKeyIdentityColumn($keyId)->deriveSameWithName($this->keys[$keyId]->getReflexivityParentColumnName());
    }

    public function getKeyReflexivityChildColumn($keyId)
    {
        return $this->getKeyIdentityColumn($keyId)->deriveSameWithName($this->keys[$keyId]->getReflexivityChildColumnName());
    }

    public function fieldTypeExists($fieldTypeId)
    {
        return array_key_exists($fieldTypeId, $this->fieldTypes);
    }

    public function keyFieldExists($keyId, $fieldId)
    {
        return $this->keyExists($keyId) && $this->keys[$keyId]->fieldExists($fieldId);
    }

    public function getKeyFieldIds($keyId, $all = true)
    {
        if ($all) {
            return $this->keys[$keyId]->getFieldIds();
        }

        return array_filter(
            $this->keys[$keyId]->getFieldIds(),
            fn ($fieldId) => $this->keys[$keyId]->isFieldVisibleInCollection($fieldId)
        );
    }

    public function getKeyFieldLabel($keyId, $fieldId)
    {
        return $this->keys[$keyId]->getFieldLabel($fieldId);
    }

    public function isKeyFieldRequired($keyId, $fieldId)
    {
        return $this->keys[$keyId]->isFieldRequired($fieldId);
    }

    public function isKeyFieldUnique($keyId, $fieldId)
    {
        return $this->keys[$keyId]->isFieldUnique($fieldId);
    }

    public function getKeyFieldTypeId($keyId, $fieldId)
    {
        return $this->keys[$keyId]->getFieldTypeId($fieldId);
    }

    public function getKeyFieldOptions($keyId, $fieldId)
    {
        $fieldType = $this->getKeyFieldType($keyId, $fieldId);

        return array_merge($fieldType->getDefaultOptions(), $this->keys[$keyId]->getFieldOptions($fieldId));
    }

    public function getKeyFieldOption($keyId, $fieldId, $optionId)
    {
        return $this->getKeyFieldOptions($keyId, $fieldId)[$optionId] ?? null;
    }

    public function getKeyFieldColumns($keyId, $fieldId)
    {
        $fieldType = $this->getKeyFieldType($keyId, $fieldId);
        $fieldOptions = $this->getKeyFieldOptions($keyId, $fieldId);
        $required = $this->isKeyFieldRequired($keyId, $fieldId);

        return $fieldType->getColumns($fieldId, $required, $fieldOptions);
    }

    public function convertKeyFieldDataToColumnData($keyId, $fieldId, $fieldData)
    {
        $fieldType = $this->getKeyFieldType($keyId, $fieldId);
        $fieldOptions = $this->getKeyFieldOptions($keyId, $fieldId);

        return $fieldType->fieldDataToColumnData($fieldId, $fieldOptions, $fieldData);
    }

    public function getKeyFieldType($keyId, $fieldId)
    {
        $fieldTypeId = $this->getKeyFieldTypeId($keyId, $fieldId);

        return $this->fieldTypes[$fieldTypeId];
    }

    public function getKeyIdentityColumnName($keyId)
    {
        return $this->keys[$keyId]->getIdColumnName();
    }

    public function getKeyIdentityColumnType($keyId)
    {
        return $this->keys[$keyId]->getIdColumnType();
    }

    public function getKeyIdentityColumn($keyId)
    {
        return $this->keys[$keyId]->getIdColumn();
    }

    public function getKeyScopeColumnName($keyId)
    {
        return $this->keys[$keyId]->getScopeColumnName();
    }

    public function getKeyScopeColumnType($keyId)
    {
        $scopeId = $this->getKeyScopeId($keyId);

        return $this->keys[$scopeId]->getIdColumnType();
    }

    public function getKeyScopeColumn($keyId)
    {
        return $this->getKeyIdentityColumn($this->getKeyScopeId($keyId))
            ->deriveSameWithName($this->getKeyScopeColumnName($keyId));
    }

    public function getKeyIsolationColumn($keyId)
    {
        return $this->getKeyIdentityColumn($this->getKeyScopeId($keyId))
            ->deriveSameWithName('_iso_'.$this->getKeyScopeColumnName($keyId));
    }

    public function getKeyIsolations($keyId, $includeSelf = false)
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

    public function getKeyIsolationColumns($keyId)
    {
        return array_map(
            fn ($iso) => $this->getKeyScopeColumn($iso),
            $this->getKeyIsolations($keyId)
        );
    }

    public function getCommonIsolation($keyA, $keyB)
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

    public function getKeyTableName($keyId)
    {
        return $this->keys[$keyId]->getTableName();
    }

    public function isKeyReferencedByField($targetKey, $sourceKey, $fieldId)
    {
        foreach ($this->getKeyFieldColumns($sourceKey, $fieldId) as $column) {
            if ($column->isReferencing($targetKey)) {
                return true;
            }
        }

        return false;
    }

    public function isKeyReferencedBy($targetKey, $sourceKey)
    {
        foreach ($this->getKeyFieldIds($sourceKey) as $fieldId) {
            if ($this->isKeyReferencedByField($targetKey, $sourceKey, $fieldId)) {
                return true;
            }
        }
    }

    public function getReferencingKeys($targetKey)
    {
        return array_filter($this->getAllKeyIds(), fn ($keyId) => $this->isKeyReferencedBy($targetKey, $keyId));
    }

    public function getReferencingKeyColumns($targetKey, $sourceKey)
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

    public function getKeySummary($keyId)
    {
        return $this->keys[$keyId]->getSummary();
    }

    public function getKeySummaryFieldIds($keyId)
    {
        return $this->getKeySummary($keyId)->getFieldIds();
    }

    public function getKeySummaryColumns($keyId)
    {
        return array_merge([], ...array_map(
            fn ($fieldId) => $this->getKeyFieldColumns($keyId, $fieldId),
            $this->getKeySummary($keyId)->getFieldIds())
        );
    }

    public function isKeyLeaf($keyId)
    {
        return empty($this->getReferencingKeys($keyId)) && empty($this->getKeyIdsScopedInside($keyId));
    }
}
