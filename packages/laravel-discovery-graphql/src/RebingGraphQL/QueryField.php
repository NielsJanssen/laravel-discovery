<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Rebing\GraphQL\Support\Query as GraphQLQuery;

class QueryField extends GraphQLQuery
{
    use AsActionField;
}
