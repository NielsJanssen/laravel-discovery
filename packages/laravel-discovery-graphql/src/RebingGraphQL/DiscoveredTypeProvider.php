<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

/** A discovered TypeProvider; only its class name is cached. */
final readonly class DiscoveredTypeProvider
{
    /**
     * @param  class-string  $class
     */
    public function __construct(
        public string $class,
    ) {}
}
