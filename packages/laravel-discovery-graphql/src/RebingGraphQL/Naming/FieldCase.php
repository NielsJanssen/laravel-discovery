<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming;

use Illuminate\Support\Str;

/** The built-in naming strategies. */
enum FieldCase: string implements NamingStrategy
{
    case Preserve = 'preserve';
    case Camel = 'camel';
    case Snake = 'snake';

    public function name(string $phpName): string
    {
        return match ($this) {
            self::Preserve => $phpName,
            self::Camel => Str::camel($phpName),
            self::Snake => Str::snake($phpName),
        };
    }
}
