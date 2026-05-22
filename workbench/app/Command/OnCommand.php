<?php

declare(strict_types=1);

namespace Workbench\App\Command;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use NielsJanssen\Laravel\Discovery\Feature\Event\EventHandler;

class OnCommand
{
    #[EventHandler]
    public function __invoke(CommandStarting $event): void
    {
        $event->output->writeln('<fg=gray>Command starting: ' . $event->command . '</>');
    }

    #[EventHandler(event: CommandFinished::class)]
    public function onFinish($event): void
    {
        $event->output->writeln('<fg=gray>Command finished: ' . $event->command . '</>');
    }
}
