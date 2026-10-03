<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;

final class UnregisteredPropertyMutation
{
    #[Mutation]
    public function schedule(UnregisteredProperty $input): bool
    {
        return true;
    }
}
