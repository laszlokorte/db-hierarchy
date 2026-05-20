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

    public function isFieldType()
    {
        return self::FLD === $this->type;
    }

    public function isLabel()
    {
        return self::LBL === $this->type;
    }

    public function isField()
    {
        return self::FLD === $this->type;
    }

    public function isId()
    {
        return self::ID === $this->type;
    }

    public function isConstant()
    {
        return self::CONSTANT === $this->direction;
    }

    public function isLocal()
    {
        return self::CONSTANT === $this->direction || self::SLF === $this->direction;
    }

    public function getFieldId()
    {
        return $this->fieldId;
    }

    public function getDirection()
    {
        return $this->direction;
    }

    public function getType()
    {
        return $this->type;
    }

    public function isNested()
    {
        return self::NST === $this->direction;
    }

    public function isParent()
    {
        return self::PAR === $this->direction;
    }

    public function isScope()
    {
        return self::SCP === $this->direction;
    }
}
