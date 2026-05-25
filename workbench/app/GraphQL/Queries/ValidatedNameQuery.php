<?php

declare(strict_types=1);

namespace Workbench\App\GraphQL\Queries;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class ValidatedNameQuery
{
    #[Query(name: 'validatedHello')]
    public function resolve(
        #[Arg(rules: ['required', 'string', 'min:3'])]
        string $name,
    ): string {
        return "hi, {$name}";
    }
}
