<?php

namespace App\Hierarchy;

class ConsistencyException extends \LogicException {
	public function __construct($msg) {
		parent::__construct($msg);
	}
}