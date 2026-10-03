<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Closure;

/** One type a TypeProvider yields: a name, a kind, and the fields built when the type is. */
final readonly class TypeDefinition
{
    /**
     * @param  Closure(TypeContext): iterable<Field>  $fields
     * @param  class-string|null  $class  the PHP class that maps to this type in inference and type resolution
     */
    public function __construct(
        public string $name,
        public Position $kind,
        public Closure $fields,
        public ?string $class = null,
        public ?string $description = null,
    ) {}
}
