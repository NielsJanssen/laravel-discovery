<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Naming\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class ExplicitOntoParameter
{
    #[Query]
    public function label(#[Arg('name')] string $title, #[Arg('label')] string $name): string
    {
        return $title . $name;
    }
}
