<?php

declare(strict_types=1);

namespace Tests\Fixtures\Schedule;

use NielsJanssen\Laravel\Discovery\Schedule\Every;
use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;

class MultipleScheduledTask
{
    #[Scheduled(Every::Hour)]
    #[Scheduled(Every::Day)]
    public function run(): void {}
}
