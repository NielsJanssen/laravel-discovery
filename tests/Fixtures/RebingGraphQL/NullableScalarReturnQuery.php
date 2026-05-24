<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class NullableScalarReturnQuery
{
    #[Query(name: 'maybeGreet')]
    public function resolve(): ?string
    {
        return null;
    }
}
