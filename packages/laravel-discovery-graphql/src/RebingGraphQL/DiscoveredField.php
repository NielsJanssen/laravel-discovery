<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use ReflectionClass;

readonly class DiscoveredField
{
    public function __construct(
        public string $fieldType,
        public string $class,
        public string $schema = 'default',
    ) {}

    public function getName(): ?string
    {
        $defaults = new ReflectionClass($this->class)->getDefaultProperties();

        return $defaults['attributes']['name'] ?? null;
    }
}
