<?php

declare(strict_types=1);

namespace Tests\Fixtures\Schedule\Traits;

use Illuminate\Console\Command;
use NielsJanssen\Laravel\Discovery\Schedule\Every;
use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;

#[Scheduled(Every::Day)]
class TraitDecoratedCommand extends Command
{
    use RunsInLondon;

    protected $signature = 'acme:trait-decorated';

    public function handle(): void {}
}
