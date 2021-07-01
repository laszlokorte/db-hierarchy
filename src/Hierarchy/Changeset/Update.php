<?php

namespace App\Hierarchy\Changeset;

class Update {
	public function __construct(private $keyId, string private $nodeId, private $columnData, private $previousData, private $errors) {
		
	}
}