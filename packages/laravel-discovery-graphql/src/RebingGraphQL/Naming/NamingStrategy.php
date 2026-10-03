<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming;

/** Turns a PHP member name into the GraphQL name of a field, an argument or an operation. */
interface NamingStrategy
{
    public function name(string $phpName): string;
}
