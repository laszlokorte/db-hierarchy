<?php

namespace App\Hierarchy\Schema\Definition;

use App\Hierarchy\Schema\Definition\ColumnDefinition;

class SummaryDefinition {
	private array $stringSegments = [];
	private array $fieldSegments = []; 

	public function __construct(array $stringSegments, array $fieldSegments) {
		if(count($stringSegments) !== count($fieldSegments) + 1) {
			throw new \Exception();
		}

		$this->stringSegments = $stringSegments;
		$this->fieldSegments = $fieldSegments;
	}

	public static function parseArray(
		array $mixSegments
	) {
		$stringSegments = [];
		$fieldSegments = []; 

		if(str_starts_with($mixSegments[0]??'', '%')) {
			$stringSegments[] = '';
			$fieldSegments[] = substr($mixSegments[0], 1);
			$stringSegments[] = '';
		} else {
			$stringSegments[] = $mixSegments[0]??'';
		}
		for ($i=1; $i <= count($mixSegments); $i+=2) { 
			$a = $mixSegments[$i]??'';
			$b = $mixSegments[$i+1]??'';

			if(str_starts_with($a, '%')) {

				if(str_starts_with($b, '%')) {
					$stringSegments[] = '';
					$fieldSegments[] = substr($a, 1);
					$stringSegments[] = '';
					$fieldSegments[] = substr($b, 1);
				} else {
					$stringSegments[] = $b;
					$fieldSegments[] = substr($a, 1);
				}
			} else {
				if(str_starts_with($b, '%')) {
					$stringSegments[] = $a;
					$fieldSegments[] = substr($b, 1);
				} else {
					$stringSegments[] = array_pop($this->stringSegments) . $a . $b;
				}
			}
		}

		return new self($stringSegments, $fieldSegments);
	}

	public static function parseString($str) {
		$parts = preg_split('/{([^}]+)}/', $str, -1, PREG_SPLIT_DELIM_CAPTURE);

		return new self(
			array_values(array_filter($parts, fn($k) => $k%2 === 0, ARRAY_FILTER_USE_KEY)),
			array_values(array_filter($parts, fn($k) => $k%2 === 1, ARRAY_FILTER_USE_KEY))
		);
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