<?php

namespace App\Form\Type\Reference;

use Symfony\Component\Form\DataTransformerInterface;

/**
 * @implements DataTransformerInterface<mixed,mixed>
 */
class ReferenceTransformer implements DataTransformerInterface
{
    public function transform(mixed $value): mixed
    {
        return $value ? $value['id'] : null;
    }

    public function reverseTransform(mixed $value): mixed
    {
        return $value ? ['id' => $value] : null;
    }
}
