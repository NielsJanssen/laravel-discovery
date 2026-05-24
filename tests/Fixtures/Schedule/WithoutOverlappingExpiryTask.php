<?php

declare(strict_types=1);

namespace Tests\Fixtures\Schedule;

use NielsJanssen\Laravel\Discovery\Schedule\Every;
use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;
use NielsJanssen\Laravel\Discovery\Schedule\WithoutOverlapping;

class WithoutOverlappingExpiryTask
{
    #[Scheduled(Every::Hour)]
    #[WithoutOverlapping(60)]
    public function run(): void {}
}
