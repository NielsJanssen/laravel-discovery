<?php

declare(strict_types=1);

namespace Tests\Fixtures\Schedule;

use Illuminate\Bus\Queueable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;

#[Scheduled(static function (Event $event) {
    $event->hourly()->onOneServer();
})]
#[Scheduled(static function (Event $event) {
    $event->daily()->withoutOverlapping();
})]
class MultipleScheduledJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function handle(): void {}
}
