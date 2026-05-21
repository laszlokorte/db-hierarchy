<?php

namespace App\Hierarchy\Schema\Definition;

enum StorageCodingType
{
    case SERIAL;
    case UUID;
    case STRING;
    case TEXT;
    case INTEGER;
    case FLOAT;
    case DECIMAL;
    case BOOL;
    case TIME;
    case DATETIME;
    case DATE;
    case BINARY;
    case ENUM;
}
