<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use GraphQL\Type\Definition\Type as GraphQLType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Action;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\ActionTypeBuilder;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class WrapInListBuilder implements ActionTypeBuilder
{
    public function buildType(Action $action): GraphQLType
    {
        return GraphQLType::listOf(GraphQLType::nonNull(GraphQLType::string()));
    }
}

class CustomBuilderQuery
{
    #[Query(name: 'wrapped')]
    #[WrapInListBuilder]
    public function resolve(): string
    {
        return 'ignored — builder controls the type';
    }
}
