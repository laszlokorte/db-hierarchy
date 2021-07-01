<?php

namespace App\Hierarchy\Changeset;

class Creation {
	public function __construct(private $keyId, ?string private $scopeId, ?string private $parentId, private $columnData, private $errors) {

	}
}