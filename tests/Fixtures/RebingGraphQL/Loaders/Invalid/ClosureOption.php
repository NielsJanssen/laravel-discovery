<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Loaders\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\Load;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use Tests\Fixtures\RebingGraphQL\Loaders\NullLoader;

#[Type]
final class ClosureOption
{
    #[Load(NullLoader::class, format: static function (string $value): string {
        return strtoupper($value);
    })]
    public ?string $label = null;
}
