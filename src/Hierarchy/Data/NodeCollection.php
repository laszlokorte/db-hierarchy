<?php

namespace App\Hierarchy\Data;

class NodeCollection implements \Countable
{
    public function __construct(private string $keyId, private array $rows, private ?string $scopeId = null, private ?string $parentId = null)
    {
    }

    public function getKey()
    {
        return $this->keyId;
    }

    public function getIds()
    {
        return array_keys($this->rows);
    }

    public function getScope()
    {
        return $this->scopeId;
    }

    public function isScoped()
    {
        return null !== $this->scopeId;
    }

    public function getParent()
    {
        return $this->parentId;
    }

    public function getColumnValue($nodeId, $columnName)
    {
        return $this->rows[$nodeId][$columnName];
    }

    public function getOrder($nodeId)
    {
        return $this->rows[$nodeId]['_order'] ?? null;
    }

    public function getNodeScope($nodeId)
    {
        return $this->rows[$nodeId]['_scope'] ?? null;
    }

    public function getNodeNestId($nodeId)
    {
        return ($this->rows[$nodeId]['_scope'] ?: '-').'/'.($this->rows[$nodeId]['_parent'] ?: '-');
    }

    public function getNode($nodeId)
    {
        return new Node(
            $this->keyId,
            $nodeId,
            $this->rows[$nodeId],
            $this->rows[$nodeId]['_scope'] ?? $this->scopeId,
            $this->rows[$nodeId]['_parent'] ?? $this->parentId,
            $this->rows[$nodeId]['_order'] ?? null,
        );
    }

    public function pathArgs($nodeId)
    {
        return ['key' => $this->keyId, 'id' => $nodeId];
    }

    public function isEmpty()
    {
        return 0 === count($this->rows);
    }

    public function count()
    {
        return count($this->rows);
    }
}
