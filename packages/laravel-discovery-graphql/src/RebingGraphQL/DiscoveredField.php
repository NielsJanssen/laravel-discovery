<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

class DiscoveredField
{
    public function __construct(
        public readonly string $fieldType,
        public readonly string $class,
        public readonly string $schema = 'default',
    ) {}

    public function getName(): ?string
    {
        $defaults = (new \ReflectionClass($this->class))->getDefaultProperties();

        return $defaults['attributes']['name'] ?? null;
    }
}
