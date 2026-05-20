<?php

namespace App\Hierarchy\Data;

use App\Hierarchy\Schema\Key;

class MultiTreeScopeIterator implements \RecursiveIterator
{
    public function __construct($tree, Key $key, $node, $depth)
    {
        $this->tree = $tree;
        $this->key = $key;
        $this->node = $node;
        $this->depth = $depth;
        $this->i = 0;
    }

    public function getChildren()
    {
        $rootKey = current($this->rootKeys);

        if ($this->key == $rootKey) {
            $scopeId = $this->node->getScope();
            $parentId = $this->node->getId();
        } else {
            $scopeId = $this->node->getScope();
            $parentId = null;
        }

        return new MultiTreeIterator($this->tree, $rootKey, $scopeId, $parentId, $this->depth + 1);
    }

    public function hasChildren(): bool
    {
        $rootKey = current($this->rootKeys);
        if ($this->key == $rootKey) {
            $scopeId = $this->node->getScope();
            $parentId = $this->node->getId();
        } else {
            $scopeId = $this->node->getScope();
            $parentId = null;
        }

        return $this->tree->hasNodes($rootKey->getId(), $scopeId, $parentId);
    }

    public function current(): mixed
    {
        return current($this->rootKeys)->getId();
    }

    public function key(): mixed
    {
        return sprintf('%s[%s]/%s', $this->key->getId(), $this->node->getId(), current($this->rootKeys)->getId() ?? '-');
    }

    public function next(): void
    {
        ++$this->i;
        array_shift($this->rootKeys);
    }

    public function rewind(): void
    {
        $this->i = 0;
        $this->rootKeys = array_filter(
            $this->key->getNestedKeys(),
            fn ($k) => $this->tree->hasAnyNodes($k->getId())
        );
    }

    public function valid(): bool
    {
        return !empty($this->rootKeys);
    }
}
