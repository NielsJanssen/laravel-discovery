<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Event;

class DiscoveredEvent
{
    /**
     * @param list<string>|string $name
     */
    public function __construct(
        public readonly array|string $name,
        public readonly EventHandler $eventHandler,
        public readonly string $class,
        public readonly string $method,
        public readonly bool $deferred,
    ) {}
}
