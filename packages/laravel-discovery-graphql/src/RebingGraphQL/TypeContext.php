<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\NamingStrategy;

/** What a TypeFactory is told about the type it contributes to. */
final readonly class TypeContext
{
    /**
     * @param  class-string|null  $class  null for a type provided without a class
     * @param  NamingStrategy  $naming  the strategy that names the type's own fields
     * @param  list<string>  $declaredFields  the GraphQL names of the fields the class declares itself
     */
    public function __construct(
        public string $name,
        public ?string $class,
        public Position $kind,
        public NamingStrategy $naming,
        public array $declaredFields,
    ) {}
}
