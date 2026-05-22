<?php

declare(strict_types=1);

namespace Tests\Fixtures\Command;

use NielsJanssen\Laravel\Discovery\Command\ConsoleCommand;

class InvokableCommand
{
    #[ConsoleCommand(
        name: 'fixture:invokable',
        description: 'Desc',
        aliases: ['fixture:inv'],
    )]
    public function __invoke(): void {}
}
