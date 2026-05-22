<?php

declare(strict_types=1);

namespace Tests\Fixtures\Command;

use NielsJanssen\Laravel\Discovery\Command\ConsoleCommand;

class OptionMiddlewareCommand
{
    #[ConsoleCommand(name: 'fixture:option-middleware', middleware: [OptionMiddleware::class])]
    public function run(): void {}
}
