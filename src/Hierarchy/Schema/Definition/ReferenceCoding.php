<?php

namespace App\Hierarchy\Schema\Definition;

class ReferenceCoding
{
    public function __construct(
        private string $target,
        private string $cascade = ReferenceCodingCascade::RESTRICT,
    ) {
    }

    public function getTarget()
    {
        return $this->target;
    }

    public function isReferencing($keyId)
    {
        return $this->target === $keyId;
    }

    public function getCascade()
    {
        return $this->cascade;
    }

    public function canCascade()
    {
        return ReferenceCodingCascade::RESTRICT !== $this->cascade;
    }
}
