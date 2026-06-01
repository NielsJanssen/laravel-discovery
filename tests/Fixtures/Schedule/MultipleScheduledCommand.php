<?php

declare(strict_types=1);

namespace Tests\Fixtures\Schedule;

use Illuminate\Console\Command;
use NielsJanssen\Laravel\Discovery\Schedule\Cron;
use NielsJanssen\Laravel\Discovery\Schedule\Every;
use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;

#[Scheduled(Every::Hour)]
#[Scheduled(new Cron('0 9 * * 1'))]
class MultipleScheduledCommand extends Command
{
    protected $signature = 'reports:dispatch';

    protected $description = 'Dispatch scheduled reports.';

    public function handle(): void {}
}
