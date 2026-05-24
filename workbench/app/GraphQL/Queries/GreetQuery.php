<?php

declare(strict_types=1);

namespace Workbench\App\GraphQL\Queries;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class GreetQuery
{
    #[Query(name: 'greet')]
    public function resolve(
        #[Arg(description: 'Name to greet')]
        string $name,
    ): string {
        return "Hello, {$name}!";
    }
}
