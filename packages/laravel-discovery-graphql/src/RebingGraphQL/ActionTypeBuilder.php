<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use GraphQL\Type\Definition\Type as GraphQLType;

interface ActionTypeBuilder
{
    public function buildType(Action $action): GraphQLType;
}
