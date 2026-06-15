<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

interface ComposedFromArgs
{
    /**
     * @param array<string, mixed> $args
     */
    public static function fromArgs(array $args): static;
}
