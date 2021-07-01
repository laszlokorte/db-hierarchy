<?php

namespace App\Hierarchy\Changeset;

class Movement {
	public function __construct(private $keyId, string private $nodeId, private $targetScope, private $targetParent, $errors) {
		
	}
}