<?php

declare(strict_types=1);

namespace Workbench\App\GraphQL\Queries;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class MaybeBookQuery
{
    #[Query(name: 'maybeBook', type: 'Book')]
    public function resolve(#[Arg] bool $found = false): ?array
    {
        return $found ? ['title' => 'De Avonden', 'author' => ['name' => 'Gerard Reve']] : null;
    }
}
