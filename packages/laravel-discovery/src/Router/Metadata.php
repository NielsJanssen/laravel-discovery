<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Router;

use Attribute;
use Illuminate\Routing\RouteGroup;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Metadata implements RouteDecorator
{
    /**
     * @param array<array-key, mixed> $metadata Metadata to attach to the routes in scope.
     */
    public function __construct(
        public array $metadata,
    ) {}

    public function decorate(Routable $route): Routable
    {
        $route->metadata = RouteGroup::mergeMetadata($route->metadata, $this->metadata);

        return $route;
    }
}
