<?php

namespace App\Hierarchy\Schema\Definition;

class ReferenceCoding {
	public const FOLLOW = 'FOLLOW';
	public const CLEAR = 'CLEAR';
	public const RESTRICT = 'RESTRICT';

	public function __construct(
		private string $target, 
		private string $cascade = 'RESTRICT'
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
}