<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Naming\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class CaseCollision
{
    #[Query]
    public function pair(string $fooBar, string $FooBar): string
    {
        return $fooBar . $FooBar;
    }
}
