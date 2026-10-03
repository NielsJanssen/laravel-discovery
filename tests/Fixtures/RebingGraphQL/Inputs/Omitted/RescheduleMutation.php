<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Omitted;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class RescheduleMutation
{
    public static ?Reschedule $received = null;

    #[Mutation]
    public function reschedule(Reschedule $input): string
    {
        self::$received = $input;

        return 'ok';
    }

    #[Query]
    public function ping(): string
    {
        return 'pong';
    }
}
