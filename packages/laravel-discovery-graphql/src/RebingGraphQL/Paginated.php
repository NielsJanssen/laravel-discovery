<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use GraphQL\Type\Definition\Type as GraphQLType;
use Rebing\GraphQL\Support\Facades\GraphQL;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
final class Paginated implements ActionTypeBuilder
{
    public function buildType(Action $action): GraphQLType
    {
        if ($action->type === null || in_array($action->type, ['string', 'int', 'float', 'bool', 'void'], true)) {
            throw new \RuntimeException('#[Paginated] requires an explicit object type on the #[Query]/#[Mutation] attribute.');
        }

        return GraphQL::paginate($action->type);
    }
}
