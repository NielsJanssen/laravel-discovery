<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use GraphQL\Type\Definition\ResolveInfo;

interface AuthorizationGate
{
    public function check(mixed $root, array $args, mixed $context, ?ResolveInfo $info): bool;
}
