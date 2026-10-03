<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

/** Contributes whole GraphQL types when Rebing's GraphQL is resolved; implement it and discovery finds it. */
interface TypeProvider
{
    /**
     * @return iterable<TypeDefinition>
     */
    public function types(): iterable;
}
