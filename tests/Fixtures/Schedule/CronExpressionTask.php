<?php

declare(strict_types=1);

namespace Tests\Fixtures\Schedule;

use NielsJanssen\Laravel\Discovery\Schedule\Cron;
use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;

class CronExpressionTask
{
    #[Scheduled(new Cron('30 6 * * 1'))]
    public function run(): void {}
}
