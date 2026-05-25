<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use GraphQL\Type\Definition\ResolveInfo;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\AuthorizationGate;

class AlwaysDenyGate implements AuthorizationGate
{
    public function check(mixed $root, array $args, mixed $context, ?ResolveInfo $info): bool
    {
        return false;
    }
}
