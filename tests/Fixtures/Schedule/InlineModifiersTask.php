<?php

declare(strict_types=1);

namespace Tests\Fixtures\Schedule;

use NielsJanssen\Laravel\Discovery\Schedule\Every;
use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;

class InlineModifiersTask
{
    #[Scheduled(Every::Hour, withoutOverlapping: true, onOneServer: true, timezone: 'UTC')]
    public function run(): void {}
}
