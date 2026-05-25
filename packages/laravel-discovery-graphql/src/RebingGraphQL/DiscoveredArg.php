<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

class DiscoveredArg
{
    public function __construct(
        public readonly string  $name,
        public readonly string  $paramName,
        public readonly string  $type,
        public readonly bool    $nullable,
        public readonly ?string $description = null,
        public readonly bool    $hasRules = false,
        public readonly bool    $hasDefault = false,
        public readonly mixed   $defaultValue = null,
        public readonly ?string $deprecationReason = null,
    ) {}
}
