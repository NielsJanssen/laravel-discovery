<?php

declare(strict_types=1);

namespace Tests\Fixtures\Schedule;

use NielsJanssen\Laravel\Discovery\Schedule\BetweenTime;
use NielsJanssen\Laravel\Discovery\Schedule\Every;
use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;

class UnlessBetweenTask
{
    #[Scheduled(Every::Hour, unlessBetween: new BetweenTime('22:00', '06:00'))]
    public function run(): void {}
}
