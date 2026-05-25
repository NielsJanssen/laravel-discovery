<?php

declare(strict_types=1);

namespace Workbench\App\GraphQL\Queries;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Middleware;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use Workbench\App\GraphQL\Middleware\ExclamationMiddleware;
use Workbench\App\GraphQL\Middleware\UppercaseMiddleware;

#[Middleware(ExclamationMiddleware::class)]
class ShoutQuery
{
    #[Query(name: 'shout')]
    #[Middleware(UppercaseMiddleware::class)]
    public function resolve(string $name): string
    {
        return "hello {$name}";
    }
}
