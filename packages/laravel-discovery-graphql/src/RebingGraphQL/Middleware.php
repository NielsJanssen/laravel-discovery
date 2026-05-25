<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Middleware
{
    /** @var list<class-string<\Rebing\GraphQL\Support\Middleware>> */
    public readonly array $middleware;

    public function __construct(string|array $middleware)
    {
        $this->middleware = is_array($middleware) ? array_values($middleware) : [$middleware];
    }
}
