<?php

namespace App\Hierarchy\Schema\Definition;

use App\Hierarchy\Schema\Definition\ColumnDefinition;

class SummaryDefinition {
	private array $stringSegments = [];
	private array $fieldSegments = []; 

	public function __construct(
		array $mixSegments
	) {
		if(str_starts_with($mixSegments[0]??'', '%')) {
			$this->stringSegments[] = '';
			$this->fieldSegments[] = substr($mixSegments[0], 1);
			$this->stringSegments[] = '';
		} else {
			$this->stringSegments[] = $mixSegments[0]??'';
		}
		for ($i=1; $i <= count($mixSegments); $i+=2) { 
			$a = $mixSegments[$i]??'';
			$b = $mixSegments[$i+1]??'';

			if(str_starts_with($a, '%')) {

				if(str_starts_with($b, '%')) {
					$this->stringSegments[] = '';
					$this->fieldSegments[] = substr($a, 1);
					$this->stringSegments[] = '';
					$this->fieldSegments[] = substr($b, 1);
				} else {
					$this->stringSegments[] = $b;
					$this->fieldSegments[] = substr($a, 1);
				}
			} else {
				if(str_starts_with($b, '%')) {
					$this->stringSegments[] = $a;
					$this->fieldSegments[] = substr($b, 1);
				} else {
					$this->stringSegments[] = array_pop($this->stringSegments) . $a . $b;
				}
			}
		}
	}

	public function __toString() {
		$result = [];

		for ($i=0; $i < count($this->stringSegments); $i++) {
			if($i>0) {
				$result[] = sprintf('[%s]', $this->fieldSegments[$i-1]);
			}
			$result[] = $this->stringSegments[$i];
		}

		return implode(',', $result);
	}

	public function getFieldIds() {
		return $this->fieldSegments;
	}

	public function getConstants() {
		return $this->stringSegments;
	}
}