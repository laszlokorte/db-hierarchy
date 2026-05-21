<?php

namespace App\Hierarchy\Schema\Definition;

enum ReferenceCodingCascade
{
    case FOLLOW;
    case CLEAR;
    case RESTRICT;
}
