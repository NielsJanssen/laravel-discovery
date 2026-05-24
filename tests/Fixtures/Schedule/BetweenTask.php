<?php

declare(strict_types=1);

namespace Tests\Fixtures\Schedule;

use NielsJanssen\Laravel\Discovery\Schedule\BetweenTime;
use NielsJanssen\Laravel\Discovery\Schedule\Every;
use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;

class BetweenTask
{
    #[Scheduled(Every::Hour, between: new BetweenTime('08:00', '17:00'))]
    public function run(): void {}
}
