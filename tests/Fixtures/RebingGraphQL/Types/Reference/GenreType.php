<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Reference;

use Rebing\GraphQL\Support\EnumType;

/** Hand-written stand-in for the enum type that enum discovery will infer from Genre. */
final class GenreType extends EnumType
{
    protected $attributes = [
        'name' => 'Genre',
        'values' => [
            'Fiction' => ['value' => Genre::Fiction],
            'Poetry' => ['value' => Genre::Poetry],
        ],
    ];
}
