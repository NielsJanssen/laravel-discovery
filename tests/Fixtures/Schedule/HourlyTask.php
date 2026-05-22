<?php

declare(strict_types=1);

namespace Tests\Fixtures\Schedule;

use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;

class HourlyTask
{
    #[Scheduled('hour')]
    public function run(): void {}
}
