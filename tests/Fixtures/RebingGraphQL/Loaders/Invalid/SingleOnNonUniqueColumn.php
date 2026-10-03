<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Loaders\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\KeyLoader;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\Load;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use Tests\Fixtures\RebingGraphQL\Loaders\Novel;

#[Type]
final class SingleOnNonUniqueColumn
{
    public int $writerId = 0;

    #[Load(KeyLoader::class, model: Novel::class, key: 'writerId', column: 'writer_id')]
    public ?Novel $novel = null;
}
