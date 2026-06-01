<?php

declare(strict_types=1);

namespace Tests\Fixtures\Schedule;

use NielsJanssen\Laravel\Discovery\Schedule\Every;
use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;

class ParameterizedCallbackTask
{
    /** @var array<string, mixed> */
    public static array $received = [];

    #[Scheduled(Every::Day, parameters: ['city' => 'Amsterdam', 'limit' => 5])]
    public function run(string $city, int $limit): void
    {
        self::$received = ['city' => $city, 'limit' => $limit];
    }
}
