<?php

declare(strict_types=1);

namespace Tests\Fixtures\Schedule;

use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;

class BasicScheduledTask
{
    #[Scheduled('15 minutes')]
    public function run(): void {}
}
