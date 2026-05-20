<?php

namespace App\Util;

use Doctrine\DBAL\Result;

class ResultFetcher
{
    public static function fetchGrouped(Result $result)
    {
        $data = [];

        foreach ($result->fetchAllAssociative() as $row) {
            $data[array_shift($row)][] = $row;
        }

        return $data;
    }
}
