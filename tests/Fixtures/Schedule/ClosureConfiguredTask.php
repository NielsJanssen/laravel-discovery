<?php

declare(strict_types=1);

namespace Tests\Fixtures\Schedule;

use Illuminate\Console\Scheduling\Event;
use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;

class ClosureConfiguredTask
{
    #[Scheduled(static function (Event $event) {
        $event->hourly()->withoutOverlapping();
    })]
    public function run(): void {}
}
