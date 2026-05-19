<?php

namespace App\Hierarchy\Schema\Definition;

class SummarySegment {
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
		private ?string $fieldId = null
	) {
	}

	public function isFieldType() {
		return $this->type === self::FLD;
	}

	public function isLabel() {
		return $this->type === self::LBL;
	}

	public function isField() {
		return $this->type === self::FLD;
	}

	public function isId() {
		return $this->type === self::ID;
	}

	public function isConstant() {
		return $this->direction === self::CONSTANT;
	}

	public function isLocal() {
		return $this->direction === self::CONSTANT || $this->direction === self::SLF;
	}

	public function getFieldId() {
		return $this->fieldId;
	}

	public function getDirection() {
		return $this->direction;
	}

	public function getType() {
		return $this->type;
	}

	public function isNested() {
		return $this->direction === self::NST;
	}

	public function isParent() {
		return $this->direction === self::PAR;
	}

	public function isScope() {
		return $this->direction === self::SCP;
	}
}