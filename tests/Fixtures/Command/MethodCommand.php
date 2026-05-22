<?php

declare(strict_types=1);

namespace Tests\Fixtures\Command;

use NielsJanssen\Laravel\Discovery\Command\ConsoleCommand;

class MethodCommand
{
    #[ConsoleCommand(name: 'fixture:method')]
    public function run(): void {}
}
