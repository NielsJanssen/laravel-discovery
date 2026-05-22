<?php

declare(strict_types=1);

namespace Tests\Fixtures\Command;

use NielsJanssen\Laravel\Discovery\Command\ConsoleCommand;

class CapturingCommand
{
    public static string $capturedName = '';

    #[ConsoleCommand(name: 'fixture:capturing')]
    public function run(string $name): void
    {
        self::$capturedName = $name;
    }
}
