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
     * @param  bool  $hasRules  #[Field(rules:)] is set, and read again by reflection when the input type is built
     * @param  DiscoveredModelBinding|null  $binding  the model an input field looks up by its route key
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
        public bool $hasRules = false,
        public ?DiscoveredModelBinding $binding = null,
        public bool $hasDefault = false,
        public mixed $defaultValue = null,
    ) {}

    /**
     * @param  list<FieldDecorator|FieldDecoratorReference>  $decorators
     */
    public function withDecorators(array $decorators): self
    {
        return clone($this, ['decorators' => $decorators]);
    }
}
