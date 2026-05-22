<?php

declare(strict_types=1);

namespace Tests\Fixtures\Command;

use NielsJanssen\Laravel\Discovery\Command\ConsoleCommand;

class MultiMiddlewareCommand
{
    #[ConsoleCommand(name: 'fixture:multi-middleware', middleware: [OuterMiddleware::class, InnerMiddleware::class])]
    public function run(): void {}
}
