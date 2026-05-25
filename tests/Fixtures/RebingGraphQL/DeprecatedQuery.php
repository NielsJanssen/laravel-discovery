<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class DeprecatedQuery
{
    #[Query(name: 'oldGreet')]
    #[\Deprecated(message: 'Use newGreet instead', since: '2.0.0')]
    public function resolve(
        #[Arg(deprecationReason: 'Pass name via context')]
        ?string $name = null,
    ): string {
        return "Hello, {$name}";
    }
}
