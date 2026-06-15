<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Router;

use BackedEnum;
use Tempest\Reflection\ClassReflector;
use Tempest\Reflection\MethodReflector;

class DiscoveredRoute
{
    /**
     * @param list<Method> $methods
     * @param list<class-string|string> $middleware
     * @param list<class-string|string> $withoutMiddleware
     */
    public function __construct(
        public array $methods,
        public string $uri,
        public string $action,
        public string|BackedEnum|null $domain = null,
        public array $middleware = [],
        public array $withoutMiddleware = [],
        public string|BackedEnum|null $name = null,
    ) {}

    /**
     * @param list<RouteDecorator> $decorators
     * @param ClassReflector<object>|MethodReflector $reflector
     */
    public static function from(Routable $route, array $decorators, ClassReflector|MethodReflector $reflector): self
    {
        foreach ($decorators as $decorator) {
            $route = $decorator->decorate($route);
        }

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
        );
    }
}
