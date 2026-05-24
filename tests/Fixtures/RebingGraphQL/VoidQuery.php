<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class VoidQuery
{
    #[Query(name: 'doNothing')]
    public function resolve(): void {}
}
