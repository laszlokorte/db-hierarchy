<?php

namespace App\Hierarchy\Changeset;

use App\Hierarchy\Data\MultiCollection;

class Deletion
{
    public function __construct(private $keyId, private string $nodeId, private MultiCollection $cascadings, private MultiCollection $blockings)
    {
    }

    public function getKeyId()
    {
        return $this->keyId;
    }

    public function getNodeId()
    {
        return $this->nodeId;
    }

    public function getCascadingKeys($excludeSelf = false)
    {
        $allKeys = $this->cascadings->getKeys();
        if ($excludeSelf) {
            return array_filter($allKeys, fn ($k) => $k !== $this->keyId || $this->cascadings->countNodesFor($k) > 1);
        }

        return $allKeys;
    }

    public function getCascadingIdsFor(string $keyId, $excludeSelf = false)
    {
        $allIds = $this->cascadings->getNodeIdsFor($keyId);

        return ($excludeSelf && $keyId === $this->keyId) ? array_filter(
            $allIds,
            fn ($nodeId) => $nodeId !== $this->nodeId
        ) : $allIds;
    }

    public function getCascadingNodeFor(string $keyId, string $nodeId)
    {
        return $this->cascadings->getNodesFor($keyId)->getNode($nodeId);
    }

    public function isCascading()
    {
        return $this->cascadings->countNodesFor($this->keyId) > 1 || $this->cascadings->countKeys() > 1;
    }

    public function getBlockings()
    {
        return $this->blockings;
    }

    public function isNotBlocked()
    {
        return 0 == $this->blockings->countKeys();
    }

    public function isBlocked()
    {
        return $this->blockings->countKeys() > 0;
    }

    public function getBlockingKeys()
    {
        return $this->blockings->getKeys();
    }

    public function getBlockingIdsFor(string $keyId)
    {
        return $this->blockings->getNodeIdsFor($keyId);
    }

    public function getBlockingNodeFor(string $keyId, string $nodeId)
    {
        return $this->blockings->getNodesFor($keyId)->getNode($nodeId);
    }
}
