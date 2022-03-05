<?php

namespace App\Hierarchy\Schema\Definition;

use App\Hierarchy\Schema\Definition\ReferenceCodingCascade;

class ReferenceCoding {
	public function __construct(
		private string $target, 
		private string $cascade = ReferenceCodingCascade::RESTRICT
	) {
	}

	public function getTarget() {
		return $this->target;
	}

	public function isReferencing($keyId) {
		return $this->target === $keyId;
	}

	public function getCascade() {
		return $this->cascade;
	}

	public function canCascade() {
		return $this->cascade !== ReferenceCodingCascade::RESTRICT;
	}
}