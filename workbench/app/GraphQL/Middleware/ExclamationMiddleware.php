<?php

declare(strict_types=1);

namespace Workbench\App\GraphQL\Middleware;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Rebing\GraphQL\Support\Middleware;

class ExclamationMiddleware extends Middleware
{
    public function handle(mixed $root, array $args, mixed $context, ResolveInfo $info, Closure $next): mixed
    {
        $result = $next($root, $args, $context, $info);

        return is_string($result) ? $result . '!' : $result;
    }
}
