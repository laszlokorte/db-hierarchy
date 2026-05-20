<?php

namespace App\Hierarchy\Data;

class MultiCollection
{
    public function __construct(
        private ?string $keyId,
        private ?string $nodeId,
        private array $groupedRows,
        private ?string $scopeId = null,
        private ?string $parentId = null,
    ) {
    }

    public function getKey()
    {
        return $this->keyId;
    }

    public function getId()
    {
        return $this->nodeId;
    }

    public function getScope()
    {
        return $this->scopeId;
    }

    public function getParent()
    {
        return $this->parentId;
    }

    public function getKeys()
    {
        return array_keys($this->groupedRows);
    }

    public function countKeys()
    {
        return count($this->groupedRows);
    }

    public function getNodesFor($keyId)
    {
        if ($keyId === $this->keyId) {
            return new NodeCollection(
                $this->keyId, $this->groupedRows[$keyId] ?? [], $this->scopeId, $this->nodeId
            );
        }

        return new NodeCollection(
            $keyId, $this->groupedRows[$keyId] ?? [], $this->nodeId, null
        );
    }

    public function getNodeIdsFor($keyId)
    {
        return array_keys($this->groupedRows[$keyId] ?? []);
    }

    public function hasNodesFor($keyId)
    {
        return !empty($this->groupedRows[$keyId]);
    }

    public function countNodesFor($keyId)
    {
        return count($this->groupedRows[$keyId]);
    }

    public function isEmpty()
    {
        return empty($this->groupedRows);
    }
}
