<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Rebing\GraphQL\Support\Type as RebingType;

/** A Rebing object type built from a TypeDefinition. */
final class ProvidedObjectType extends RebingType
{
    public function __construct(
        private readonly ProvidedFields $fields,
    ) {}

    public function attributes(): array
    {
        return $this->fields->attributes();
    }

    public function fields(): array
    {
        return $this->fields->build();
    }
}
