<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Rebing\GraphQL\Support\Middleware;

class UppercaseMiddleware extends Middleware
{
    public function handle(mixed $root, array $args, mixed $context, ResolveInfo $info, Closure $next): mixed
    {
        $result = $next($root, $args, $context, $info);

        return is_string($result) ? strtoupper($result) : $result;
    }
}
