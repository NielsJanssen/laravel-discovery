<?php

declare(strict_types=1);

namespace Tests\Fixtures\Schedule;

use NielsJanssen\Laravel\Discovery\Schedule\Every;
use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;

#[Scheduled(Every::Day)]
class ClassScheduledNonCommand
{
    public function run(): void {}
}
