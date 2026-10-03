<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use GraphQL\Type\Definition\Type as GraphQLType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Action;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\ActionTypeBuilder;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class NonNullListBuilder implements ActionTypeBuilder
{
    public function buildType(Action $action): GraphQLType
    {
        return GraphQLType::nonNull(GraphQLType::listOf(GraphQLType::nonNull(GraphQLType::string())));
    }
}

class NonNullBuilderQuery
{
    /** @return list<string> */
    #[Query(name: 'nonNullBuilt')]
    #[NonNullListBuilder]
    public function resolve(): array
    {
        return [];
    }
}
