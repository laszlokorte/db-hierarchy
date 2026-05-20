<?php

namespace App\Hierarchy\Schema;

use App\Hierarchy\Data\Node;
use App\Hierarchy\Schema\Definition\LabelDefinition;
use App\Hierarchy\Schema\Definition\SchemaDefinition;

class Key
{
    public function __construct(
        private SchemaDefinition $def,
        private string $keyId,
    ) {
    }

    public function getId(): string
    {
        return $this->keyId;
    }

    public function hasField(string $fieldId): bool
    {
        return $this->def->keyFieldExists($this->keyId, $fieldId);
    }

    public function getField(string $fieldId): Field
    {
        return new Field($this->def, $this->keyId, $fieldId);
    }
    /**
     * @param mixed $all
     */
    public function getFields($all = true): array
    {
        return array_map([$this, 'getField'], $this->def->getKeyFieldIds($this->keyId, $all));
    }

    public function getLabel(): LabelDefinition
    {
        return $this->def->getKeyLabel($this->keyId);
    }

    public function isReflexive() : bool
    {
        return $this->def->isKeyReflexive($this->keyId);
    }

    public function isOrdered(): bool
    {
        return $this->def->isKeyOrdered($this->keyId);
    }

    public function getOrderColumnName(): string
    {
        return $this->def->getKeyOrderColumnName($this->keyId);
    }

    public function isScoped(): bool
    {
        return $this->def->isKeyScoped($this->keyId);
    }

    public function getScopeKey(): ?Key
    {
        if (!$this->isScoped()) {
            return null;
        }

        return new Key($this->def, $this->def->getKeyScopeId($this->keyId));
    }
    /**
     * @return Key[]
     */
    public function getScopeChildKeys(bool $singletons = true, bool $skipAtoms = false): array
    {
        return array_map(
            fn ($k) => new Key($this->def, $k),
            $this->def->getKeyIdsScopedInside($this->keyId, $singletons, $skipAtoms)
        );
    }
    /**
     * @return Key[]
     */
    public function getNestedKeys(): array
    {
        return array_map(
            fn ($k) => new Key($this->def, $k),
            $this->def->getKeyIdsScopedInsideAndReflexiveSelf($this->keyId)
        );
    }
    /**
     * @return Key[]
     */
    public function getNestingPath(): array
    {
        return array_map(
            fn ($k) => new Key($this->def, $k),
            $this->def->getKeyScopePath($this->keyId, true)
        );
    }
    /**
     * @return Key[]
     */
    public function getIsolations(): array
    {
        return array_map(
            fn ($k) => new Key($this->def, $k),
            $this->def->getKeyIsolations($this->keyId)
        );
    }
    /**
     * @param mixed $otherKey
     */
    public function commonIsolation(string $otherKey): ?array
    {
        return $this->def->getCommonIsolation($this->keyId, $otherKey);
    }

    public function isNested(): bool
    {
        return $this->def->isKeyNested($this->keyId);
    }

    public function isSingleton() : bool
    {
        return $this->def->isKeySingleton($this->keyId);
    }

    public function isAtomic(): bool
    {
        return $this->def->isKeyAtomic($this->keyId);
    }
    /**
     * @return Key[]
     */
    public function getReferencingKeys(): array
    {
        return array_map(
            fn ($k) => new Key($this->def, $k),
            $this->def->getReferencingKeys($this->keyId)
        );
    }

    public function getReferencingKey(string $keyId): Key
    {
        if (!$this->def->isKeyReferencedBy($keyId, $this->keyId)) {
            throw new \Exception(sprintf('%s is not in %s', $keyId, implode(', ', $this->def->getReferencingKeys($this->keyId))));
        }

        return new Key($this->def, $keyId);
    }

    public function getNodeFieldValues(Node $node): array
    {
        $fieldIds = $this->def->getKeyFieldIds($this->keyId);

        return array_combine(
            $fieldIds,
            array_map(fn ($fieldId) => $this->getField($fieldId)->readValueOf($node), $fieldIds)
        );
    }

    public function getSummary() : string
    {
        return $this->def->getKeySummary($this->keyId);
    }

    public function summarize(Node $node, ?string $appendId = null): string
    {
        $summDef = $this->def->getKeySummary($this->keyId);

        $result = '';

        $ambiguous = true;

        foreach ($summDef->getSegments() as $seg) {
            if ($seg->isConstant()) {
                $val = $seg->getType();
            } elseif ($seg->isLocal()) {
                if ($seg->isLabel()) {
                    $val = $this->getLabel()->getString();
                } elseif ($seg->isId()) {
                    $val = $node->getId();
                    $ambiguous &= false;
                } elseif ($seg->isField()) {
                    $field = $this->getField($seg->getFieldId());

                    $val = $field->readFormattedValueOf($node);

                    if ($field->isUnique()) {
                        $ambiguous &= false;
                    }
                }
            } elseif ($seg->isNested()) {
                if ($seg->isId()) {
                    $val = $node->getParent() ?: $node->getScope();
                } elseif ($seg->isLabel()) {
                    $val = $node->getParent() ? $this->getLabel()->getString() : $this->getScopeKey()->getLabel()->getString();
                } else {
                    $val = '?';
                }
            } elseif ($seg->isParent()) {
                if ($seg->isId()) {
                    $val = $node->getParent();
                } elseif ($seg->isLabel()) {
                    $val = $this->getLabel()->getString();
                } else {
                    $val = '?';
                }
            } elseif ($seg->isScope()) {
                if ($seg->isId()) {
                    $val = $node->getScope();
                } elseif ($seg->isLabel()) {
                    $val = $this->getScopeKey()->getLabel()->getString();
                } else {
                    $val = '?';
                }
            }

            $result .= $val;
        }

        if (true === $appendId || null === $appendId && empty($result)) {
            $result .= ' ['.$node->getKey().'-'.$node->getId().']';
        } elseif (true === $ambiguous) {
            $result .= ' ['.$node->getId().']';
        }

        return $result;
    }
}
