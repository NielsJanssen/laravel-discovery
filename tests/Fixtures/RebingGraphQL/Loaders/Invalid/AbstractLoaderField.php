<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Loaders\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\Load;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class AbstractLoaderField
{
    #[Load(AbstractLoader::class)]
    public ?string $label = null;
}
