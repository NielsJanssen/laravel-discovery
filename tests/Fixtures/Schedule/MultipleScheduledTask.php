<?php

declare(strict_types=1);

namespace Tests\Fixtures\Schedule;

use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;

class MultipleScheduledTask
{
    #[Scheduled('hour')]
    #[Scheduled('day')]
    public function run(): void {}
}
