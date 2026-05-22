<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Event;

use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Discovery\SkipDiscovery;
use Tempest\Reflection\ClassReflector;
use Tempest\Reflection\MethodReflector;
use Tempest\Reflection\TypeReflector;

#[SkipDiscovery]
final class EventDiscovery implements Discovery
{
    use IsDiscovery;

    public function __construct(
        private readonly Application $app,
        private readonly Dispatcher $eventDispatcher,
    ) {}

    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        foreach ($class->getPublicMethods() as $method) {
            $eventHandler = $method->getAttribute(EventHandler::class);

            if (! $eventHandler) {
                continue;
            }

            $eventName = $eventHandler->event ?? $this->resolveEventName($method);

            if ($eventName) {
                $this->discoveryItems->add($location, [$eventName, $eventHandler, $method, $eventHandler->deferred]);
            }
        }
    }

    public function apply(): void
    {
        foreach ($this->discoveryItems as [$eventName, $eventHandler, $method, $deferred]) {
            $class = $method->getDeclaringClass()->getName();

            $register = fn() => $this->eventDispatcher->listen(
                events: $eventName,
                listener: $method->getDeclaringClass()->getName() . '@' . $method->getName(),
            );

            if (!$deferred || $this->app->resolved($class)) {
                $register();
                continue;
            }

            $this->app->afterResolving($class, $register);
        }
    }

    private function resolveEventName(MethodReflector $method): ?array
    {
        $parameters = iterator_to_array($method->getParameters());

        if ($parameters === []) {
            return null;
        }

        /** @var TypeReflector $type */
        $type = $parameters[0]->getType();

        if ($type->isUnion()) {
            $types = [];

            foreach ($type->split() as $unionType) {
                if ($unionType->isClass() || $unionType->isInterface()) {
                    $types[] = $unionType->getName();
                }
            }

            return $types;
        }

        if (! $type->isClass() && ! $type->isInterface()) {
            return null;
        }

        return [$type->getName()];
    }
}
