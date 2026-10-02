<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\ClassifiedParameters;

/** One field of a discovered type, as a serializable description. */
final readonly class DiscoveredTypeField
{
    /**
     * @param  list<object>  $decorators  attribute instances without closures
     */
    public function __construct(
        public string $phpName,
        public string $name,
        public TypeRef $type,
        public FieldSource $source = FieldSource::Property,
        public ClassifiedParameters $parameters = new ClassifiedParameters(),
        public ?string $description = null,
        public ?string $deprecationReason = null,
        public array $decorators = [],
    ) {}
}
