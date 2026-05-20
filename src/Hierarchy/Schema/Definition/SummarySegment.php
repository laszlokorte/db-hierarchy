<?php

namespace App\Hierarchy\Schema\Definition;

class SummarySegment
{
    public const CONSTANT = 'CONSTANT';
    public const SLF = 'SELF';
    public const SCP = 'SCOPE';
    public const PAR = 'PARENT';
    public const NST = 'NESTING';

    public const SMR = 'SUMMARY';
    public const FLD = 'FIELD';
    public const LBL = 'LABEL';
    public const ID = 'ID';

    public function __construct(
        private string $direction,
        private string $type,
        private ?string $fieldId = null,
    ) {
    }

    public function isFieldType(): bool
    {
        return self::FLD === $this->type;
    }

    public function isLabel(): bool
    {
        return self::LBL === $this->type;
    }

    public function isField(): bool
    {
        return self::FLD === $this->type;
    }

    public function isId(): bool
    {
        return self::ID === $this->type;
    }

    public function isConstant(): bool
    {
        return self::CONSTANT === $this->direction;
    }

    public function isLocal(): bool
    {
        return self::CONSTANT === $this->direction || self::SLF === $this->direction;
    }

    public function getFieldId(): ?string
    {
        return $this->fieldId;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isNested(): bool
    {
        return self::NST === $this->direction;
    }

    public function isParent(): bool
    {
        return self::PAR === $this->direction;
    }

    public function isScope(): bool
    {
        return self::SCP === $this->direction;
    }
}
