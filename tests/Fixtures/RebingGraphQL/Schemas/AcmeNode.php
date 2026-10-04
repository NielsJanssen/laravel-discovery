<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Schemas;

use GraphQL\Type\Definition\Type as GraphQLType;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Rebing\GraphQL\Support\InterfaceType;

final class AcmeNode extends InterfaceType
{
    protected $attributes = [
        'name' => 'AcmeNode',
    ];

    public function fields(): array
    {
        return [
            'id' => ['type' => GraphQLType::nonNull(GraphQLType::id())],
        ];
    }

    public function resolveType(mixed $root): GraphQLType
    {
        return GraphQL::type('AcmeWidget');
    }
}
