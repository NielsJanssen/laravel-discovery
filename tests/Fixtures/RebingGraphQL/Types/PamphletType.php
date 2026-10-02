<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types;

use GraphQL\Type\Definition\Type as GraphQLType;
use Rebing\GraphQL\Support\Type as RebingType;

final class PamphletType extends RebingType
{
    protected $attributes = [
        'name' => 'Pamphlet',
    ];

    public function fields(): array
    {
        return [
            'title' => ['type' => GraphQLType::nonNull(GraphQLType::string())],
        ];
    }
}
