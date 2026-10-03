<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Inference\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class UnregisteredArgQuery
{
    #[Query]
    public function find(#[Arg('thing', type: Unregistered::class)] mixed $value): string
    {
        return 'never';
    }
}
