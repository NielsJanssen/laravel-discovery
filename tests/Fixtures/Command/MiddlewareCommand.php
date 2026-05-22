<?php

declare(strict_types=1);

namespace Tests\Fixtures\Command;

use NielsJanssen\Laravel\Discovery\Command\ConsoleCommand;

class MiddlewareCommand
{
    #[ConsoleCommand(name: 'fixture:middleware', middleware: [RecordingMiddleware::class])]
    public function run(): void {}
}
