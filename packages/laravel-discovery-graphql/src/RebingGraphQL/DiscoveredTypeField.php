<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\ClassifiedParameters;

/** One field of a discovered type, as a serializable description. */
final readonly class DiscoveredTypeField
{
    /**
     * @param  list<FieldDecorator|FieldDecoratorReference>  $decorators  serializable instances, or references to re-read
     * @param  class-string|null  $typeClass  the #[Type] class the field belongs to
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
        public ?string $typeClass = null,
    ) {}

    /**
     * @param  list<FieldDecorator|FieldDecoratorReference>  $decorators
     */
    public function withDecorators(array $decorators): self
    {
        return clone($this, ['decorators' => $decorators]);
    }
}
