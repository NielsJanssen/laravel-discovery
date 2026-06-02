<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Router;

use Attribute;
use BackedEnum;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Route implements Routable
{
    /**
     * @param list<Method> $methods
     * @param list<class-string|string> $middleware Middleware specific to this route.
     * @param list<class-string|string> $withoutMiddleware Middleware to remove from this route.
     */
    public function __construct(
        public array                  $methods,
        public string                 $uri,
        public array                  $middleware = [],
        public array                  $withoutMiddleware = [],
        public string|BackedEnum|null $domain = null,
        public string|BackedEnum|null $name = null,
    ) {}
}
