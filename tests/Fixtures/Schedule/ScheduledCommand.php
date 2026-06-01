<?php

declare(strict_types=1);

namespace Tests\Fixtures\Schedule;

use Illuminate\Console\Command;
use NielsJanssen\Laravel\Discovery\Schedule\Every;
use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;

#[Scheduled(Every::Day)]
class ScheduledCommand extends Command
{
    protected $signature = 'fixture:scheduled';

    protected $description = 'A traditional Laravel command scheduled at the class level';

    public function handle(): void {}
}
