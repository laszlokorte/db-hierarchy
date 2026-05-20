<?php

namespace App\Hierarchy\Data;

use App\Hierarchy\Changeset\Update;

class Node
{
    /**
     * @param array<int,mixed> $columns
     */
    public function __construct(private string $keyId, private string $nodeId, private array $columns, private ?string $scopeId = null, private ?string $parentId = null, private ?int $order = null)
    {
    }

    public function getKey(): string
    {
        return $this->keyId;
    }

    public function getId(): string
    {
        return $this->nodeId;
    }

    public function getScope(): ?string
    {
        return $this->scopeId;
    }

    public function getOrder(): ?int
    {
        return $this->order;
    }

    public function getParent(): ?string
    {
        return $this->parentId;
    }

    public function hasScope(): bool
    {
        return !empty($this->scopeId);
    }

    public function hasParent(): bool
    {
        return !empty($this->parentId);
    }
    /**
     * @return array<int,mixed>
     */
    public function getColumnValues(): array
    {
        return $this->columns;
    }

    public function getColumnValue(string $columnName) : mixed
    {
        return $this->columns[$columnName];
    }
    /**
     * @return array<string,string>
     */
    public function pathArgs(): array
    {
        return ['keyId' => $this->keyId, 'nodeId' => $this->nodeId];
    }

    public function newUpdate(): Update
    {
        return new Update(
            $this->keyId,
            $this->nodeId,
            [],
            $this->columns,
            []
        );
    }

    public function __toString() : string
    {
        return $this->scopeId.'/'.$this->nodeId;
    }

    public function asNestingFor(string $keyId): NodeNesting
    {
        if ($this->keyId === $keyId) {
            return new NodeNesting($keyId, $this->scopeId, $this->nodeId);
        }

        return new NodeNesting($keyId, $this->nodeId, null);
    }

    public function getNesting(): NodeNesting
    {
        return new NodeNesting($this->keyId, $this->scopeId, $this->parentId);
    }
}
