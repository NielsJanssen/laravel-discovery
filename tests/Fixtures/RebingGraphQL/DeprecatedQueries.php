<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class DeprecatedQueries
{
    #[Query(name: 'oldGreet')]
    #[\Deprecated(message: 'Use newGreet instead', since: '2.0.0')]
    public function withMessage(
        #[Arg(deprecationReason: 'Pass name via context')]
        ?string $name = null,
    ): string {
        return "Hello, {$name}";
    }

    #[Query]
    #[\Deprecated(since: '3.0.0')]
    public function sinceOnly(): string
    {
        return 'ok';
    }

    #[Query(name: 'bareDeprecated')]
    #[\Deprecated]
    public function bare(): string
    {
        return 'ok';
    }
}
