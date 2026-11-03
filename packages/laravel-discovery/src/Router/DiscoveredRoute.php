<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Router;

use BackedEnum;
use Illuminate\Routing\RouteGroup;
use Tempest\Reflection\ClassReflector;
use Tempest\Reflection\MethodReflector;

class DiscoveredRoute
{
    /**
     * @param list<Method> $methods
     * @param list<class-string|string> $middleware
     * @param list<class-string|string> $withoutMiddleware
     * @param array<array-key, mixed> $metadata
     */
    public function __construct(
        public array $methods,
        public string $uri,
        public string $action,
        public string|BackedEnum|null $domain = null,
        public array $middleware = [],
        public array $withoutMiddleware = [],
        public string|BackedEnum|null $name = null,
        public array $metadata = [],
    ) {}

    /**
     * @param list<RouteDecorator> $decorators
     * @param ClassReflector<object>|MethodReflector $reflector
     */
    public static function from(Routable $route, array $decorators, ClassReflector|MethodReflector $reflector): self
    {
        $metadata        = $route->metadata;
        $route->metadata = [];

        foreach ($decorators as $decorator) {
            $route = $decorator->decorate($route);
        }

        // The attribute's own metadata is the innermost layer, so it is merged over every decorator.
        $route->metadata = RouteGroup::mergeMetadata($route->metadata, $metadata);

        return new self(
            $route->methods,
            $route->uri,
            $reflector instanceof MethodReflector
                ? $reflector->getDeclaringClass()->getName() . '@' . $reflector->getName()
                : $reflector->getName(),
            $route->domain,
            $route->middleware,
            $route->withoutMiddleware,
            $route->name,
            $route->metadata,
        );
    }
}
