<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Router;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
final class Post implements Route
{
    public Method $method = Method::Post;

    /**
     * @param class-string<class-string|string>[] $middleware Middleware specific to this route.
     * @param class-string<class-string|string>[] $withoutMiddleware Middleware to remove from this route.
     */
    public function __construct(
        public string $uri,
        public array $middleware = [],
        public array $withoutMiddleware = [],
        public string|\BackedEnum|null $domain = null,
        public string|\BackedEnum|null $name = null,
    ) {}
}
