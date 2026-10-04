<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Schemas;

use GraphQL\Type\Definition\Type as GraphQLType;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Rebing\GraphQL\Support\Type as RebingType;

final class AcmeWidget extends RebingType
{
    protected $attributes = [
        'name' => 'AcmeWidget',
    ];

    public function fields(): array
    {
        return [
            'id' => ['type' => GraphQLType::nonNull(GraphQLType::id())],
        ];
    }

    public function interfaces(): array
    {
        return [GraphQL::type('AcmeNode')];
    }
}
