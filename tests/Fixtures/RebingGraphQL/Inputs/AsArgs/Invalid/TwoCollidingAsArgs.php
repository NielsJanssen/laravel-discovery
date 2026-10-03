<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\AsArgs\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\AsArgs;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use Tests\Fixtures\RebingGraphQL\Inputs\AsArgs\BookSearch;

final class TwoCollidingAsArgs
{
    #[Query]
    public function compare(#[AsArgs] BookSearch $left, #[AsArgs] BookSearch $right): string
    {
        return 'never';
    }
}
