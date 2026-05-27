<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Rebing\GraphQL\Support\Mutation as GraphQLMutation;

final class MutationField extends GraphQLMutation
{
    use AsActionField;
}
