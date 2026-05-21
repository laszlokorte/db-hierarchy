<?php

namespace App\Hierarchy\Schema\Definition;

class ReferenceCoding
{
    public function __construct(
        private string $target,
        private ReferenceCodingCascade $cascade = ReferenceCodingCascade::RESTRICT,
    ) {
    }

    public function getTarget(): string
    {
        return $this->target;
    }

    public function isReferencing(string $keyId): bool
    {
        return $this->target === $keyId;
    }

    public function getCascade(): ReferenceCodingCascade
    {
        return $this->cascade;
    }

    public function canCascade(): bool
    {
        return ReferenceCodingCascade::RESTRICT !== $this->cascade;
    }
}
