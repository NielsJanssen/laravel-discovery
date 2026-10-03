<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Loaders\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\KeyLoader;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\Load;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use Tests\Fixtures\RebingGraphQL\Loaders\Writer;

#[Type]
final class KeyWithoutModel
{
    public int $writerId = 0;

    #[Load(KeyLoader::class, key: 'writerId')]
    public ?Writer $writer = null;
}
