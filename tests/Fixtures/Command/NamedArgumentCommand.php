<?php

declare(strict_types=1);

namespace Tests\Fixtures\Command;

use NielsJanssen\Laravel\Discovery\Feature\Command\ConsoleArgument;
use NielsJanssen\Laravel\Discovery\Feature\Command\ConsoleCommand;

class NamedArgumentCommand
{
    #[ConsoleCommand(name: 'fixture:named-argument')]
    public function run(
        #[ConsoleArgument(name: 'custom-name')]
        string $originalName = 'default',
    ): void {}
}
