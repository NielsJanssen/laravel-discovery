<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Inference\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use Tests\Fixtures\RebingGraphQL\Types\Inference\Novel;

final class ObjectTypeArgQuery
{
    #[Query]
    public function review(#[Arg(type: Novel::class)] mixed $novel): string
    {
        return 'never';
    }
}
