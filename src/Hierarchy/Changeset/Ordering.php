<?php

namespace App\Hierarchy\Changeset;

class Ordering {
	public function __construct(private $keyId, string private $nodeId, private $targetOrder, $errors) {
		
	}
}