<?php

namespace App\Request\ParamConverter;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class HierarchySlug
{
    public function __construct(
        public string $slug,
    ) {
    }
}
