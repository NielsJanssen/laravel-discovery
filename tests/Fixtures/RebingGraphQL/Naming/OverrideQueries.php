<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Naming;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class OverrideQueries
{
    #[Query]
    public function plainVolume(): PlainVolume
    {
        return new PlainVolume();
    }

    #[Mutation]
    public function keepNote(SnakeNote $noteInput): SnakeNote
    {
        return $noteInput;
    }
}
