<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Loaders\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\KeyLoader;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\Load;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use Tests\Fixtures\RebingGraphQL\Loaders\Writer;

#[Type]
final class UnknownOption
{
    public int $writerId = 0;

    #[Load(KeyLoader::class, model: Writer::class, key: 'writerId', colum: 'id')]
    public ?Writer $writer = null;
}
