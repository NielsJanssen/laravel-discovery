<?php

declare(strict_types=1);

namespace Tests\Fixtures\Command;

use NielsJanssen\Laravel\Discovery\Feature\Command\ConsoleCommand;

class ShortCircuitCommand
{
    #[ConsoleCommand(name: 'fixture:short-circuit', middleware: [ShortCircuitMiddleware::class])]
    public function run(): void
    {
        // This should never be reached when ShortCircuitMiddleware is active
        throw new \RuntimeException('Inner command was executed — short-circuit failed!');
    }
}
