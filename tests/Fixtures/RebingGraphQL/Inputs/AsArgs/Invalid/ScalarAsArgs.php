<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\AsArgs\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\AsArgs;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class ScalarAsArgs
{
    #[Query]
    public function search(#[AsArgs] string $term): string
    {
        return $term;
    }
}
