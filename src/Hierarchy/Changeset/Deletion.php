<?php

namespace App\Hierarchy\Changeset;

class Deletion {
	public function __construct(private $keyId, string private $nodeId, private $cascade, private $blocking) {
		
	}
}