<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

readonly class DiscoveredArg
{
    public function __construct(
        public string  $name,
        public string  $paramName,
        public TypeRef $type,
        public ?string $description = null,
        public bool    $hasRules = false,
        public bool    $hasDefault = false,
        public mixed   $defaultValue = null,
        public ?string $deprecationReason = null,
        /** The value is an #[Input] object, hydrated into the type's class before the resolver runs. */
        public bool $input = false,
    ) {}
}
