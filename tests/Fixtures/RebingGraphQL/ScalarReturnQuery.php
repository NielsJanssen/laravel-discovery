<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class ScalarReturnQuery
{
    #[Query(name: 'greet')]
    public function resolve(string $name): string
    {
        return "Hello, {$name}!";
    }
}
