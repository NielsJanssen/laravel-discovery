<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Inference\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;

final class UnregisteredListMutation
{
    /** @return list<Unregistered> */
    #[Mutation(of: Unregistered::class)]
    public function things(): array
    {
        return [];
    }
}
