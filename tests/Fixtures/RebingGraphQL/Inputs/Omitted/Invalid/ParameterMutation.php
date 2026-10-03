<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Omitted\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Omitted;

final class ParameterMutation
{
    #[Mutation]
    public function rename(string|Omitted $title = Omitted::Value): string
    {
        return 'ok';
    }
}
