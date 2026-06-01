<?php

declare(strict_types=1);

namespace Tests\Fixtures\Schedule;

use Illuminate\Console\Command;
use NielsJanssen\Laravel\Discovery\Schedule\Every;
use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;

#[Scheduled(Every::Day, parameters: ['--queue' => 'orders'])]
class ParameterizedScheduledCommand extends Command
{
    protected $signature = 'inventory:rebuild {--queue=}';

    protected $description = 'Rebuild the inventory projection.';

    public function handle(): void {}
}
