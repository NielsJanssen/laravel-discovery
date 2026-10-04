<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Rebing\GraphQL\GraphQL;

/** Rebing's GraphQL with each schema limited to its own types and the global ones. */
final class ScopedGraphQL extends GraphQL
{
    use ScopesSchemaTypes;
}
