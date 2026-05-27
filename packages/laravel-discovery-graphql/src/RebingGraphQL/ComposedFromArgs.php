<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

interface ComposedFromArgs
{
    public static function fromArgs(array $args): static;
}
