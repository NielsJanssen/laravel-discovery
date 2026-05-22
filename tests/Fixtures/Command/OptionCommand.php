<?php

declare(strict_types=1);

namespace Tests\Fixtures\Command;

use NielsJanssen\Laravel\Discovery\Command\ConsoleCommand;
use NielsJanssen\Laravel\Discovery\Command\ConsoleOption;

class OptionCommand
{
    #[ConsoleCommand(name: 'fixture:option')]
    public function run(
        #[ConsoleOption]
        bool $verbose = false,
        #[ConsoleOption(shortcut: 'u')]
        string $user = '',
    ): void {}
}
