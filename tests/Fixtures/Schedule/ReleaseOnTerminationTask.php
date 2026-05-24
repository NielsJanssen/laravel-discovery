<?php

declare(strict_types=1);

namespace Tests\Fixtures\Schedule;

use NielsJanssen\Laravel\Discovery\Schedule\Every;
use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;
use NielsJanssen\Laravel\Discovery\Schedule\WithoutOverlapping;

class ReleaseOnTerminationTask
{
    #[Scheduled(Every::Hour)]
    #[WithoutOverlapping(60, releaseOnTerminationSignals: false)]
    public function run(): void {}
}
