<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Attribute;

/** Adds the #[Field] methods of a class, and the fields of its TypeFactory, to an existing output type. */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class TypeExtension
{
    /**
     * @param  string  $type  the class of the type, or its GraphQL name
     */
    public function __construct(
        public string $type,
    ) {}
}
