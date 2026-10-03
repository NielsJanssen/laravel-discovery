<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Middleware;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use Workbench\App\GraphQL\Middleware\ExclamationMiddleware;
use Workbench\App\GraphQL\Middleware\UppercaseMiddleware;

#[Middleware(ExclamationMiddleware::class)]
class MiddlewareQuery
{
    #[Query(name: 'shout')]
    #[Middleware(UppercaseMiddleware::class)]
    public function resolve(): string
    {
        return 'hello';
    }

    #[Query(name: 'whisper')]
    public function whisper(): string
    {
        return 'hello';
    }
}
