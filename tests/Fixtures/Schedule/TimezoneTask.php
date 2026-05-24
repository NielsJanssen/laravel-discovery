<?php

declare(strict_types=1);

namespace Tests\Fixtures\Schedule;

use NielsJanssen\Laravel\Discovery\Schedule\Every;
use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;
use NielsJanssen\Laravel\Discovery\Schedule\Timezone;

class TimezoneTask
{
    #[Scheduled(Every::Hour)]
    #[Timezone('Europe/Amsterdam')]
    public function run(): void {}
}
