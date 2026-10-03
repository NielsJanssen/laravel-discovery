<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\AsArgs;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\AsArgs;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class OrderMutations
{
    public static ?PlaceOrder $received = null;

    public static ?string $channel = null;

    #[Mutation]
    public function placeOrder(string $channel, #[AsArgs] PlaceOrder $order): string
    {
        self::$received = $order;
        self::$channel = $channel;

        return $order->title;
    }

    #[Query]
    public function ping(): string
    {
        return 'pong';
    }
}
