<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Rebing\GraphQL\Support\InputType as RebingInputType;

/** A Rebing input object type built from a TypeDefinition. */
final class ProvidedInputType extends RebingInputType
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
