<?php

declare(strict_types=1);

namespace Tests\Fixtures\Schedule;

use NielsJanssen\Laravel\Discovery\Schedule\Every;
use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;

class BasicScheduledTask
{
    #[Scheduled(Every::FifteenMinutes)]
    public function run(): void {}
}
