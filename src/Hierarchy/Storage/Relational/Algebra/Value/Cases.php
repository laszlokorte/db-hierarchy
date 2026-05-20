<?php

namespace App\Hierarchy\Storage\Relational\Algebra\Value;

class Cases implements ValueInterface
{
    private $conditions;
    private $consequences;
    private $fallback;

    public function __construct(...$cases)
    {
        for ($i = 1; $i < count($cases); $i += 2) {
            $this->conditions[] = $cases[$i - 1];
            $this->consequences[] = $cases[$i];
        }

        if (count($cases) % 2) {
            $this->fallback = $cases[count($cases) - 1];
        }
    }

    public function getFallback()
    {
        return $this->fallback;
    }

    public function count()
    {
        return count($this->conditions);
    }

    public function getCondition($i)
    {
        return $this->conditions[$i];
    }

    public function getConsequence($i)
    {
        return $this->consequences[$i];
    }
}
