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
        /** the type a TypeMapper claimed; wins over $type */
        public ?TypeRef $typeRef = null,
        /** The value is an #[Input] object, hydrated into $type before the resolver runs. */
        public bool $input = false,
    ) {}

    public function ref(): TypeRef
    {
        return $this->typeRef ?? TypeRef::from($this->type, nullable: $this->nullable);
    }
}
