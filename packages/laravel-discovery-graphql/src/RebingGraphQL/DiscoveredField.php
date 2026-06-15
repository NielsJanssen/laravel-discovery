<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use ReflectionClass;

readonly class DiscoveredField
{
    /**
     * @param class-string $class
     */
    public function __construct(
        public string $fieldType,
        public string $class,
        public string $schema = 'default',
    ) {}

    public function getName(): ?string
    {
        $defaults = new ReflectionClass($this->class)->getDefaultProperties();

        if (isset($defaults['attributes']) && is_array($defaults['attributes']) && isset($defaults['attributes']['name']) && is_string($defaults['attributes']['name'])) {
            return $defaults['attributes']['name'];
        }

        return null;
    }
}
