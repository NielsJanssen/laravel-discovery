<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class EventMutation
{
    public static ?Event $received = null;

    #[Mutation]
    public function schedule(Event $event): string
    {
        self::$received = $event;

        return $event->startsAt->toDateString();
    }

    #[Query]
    public function ping(): string
    {
        return 'pong';
    }
}
