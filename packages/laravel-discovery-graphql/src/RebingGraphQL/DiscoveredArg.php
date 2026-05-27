<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

readonly class DiscoveredArg
{
    public function __construct(
        public string  $name,
        public string  $paramName,
        public string  $type,
        public bool    $nullable,
        public ?string $description = null,
        public bool    $hasRules = false,
        public bool    $hasDefault = false,
        public mixed   $defaultValue = null,
        public ?string $deprecationReason = null,
    ) {}
}
