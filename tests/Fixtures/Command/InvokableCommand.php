<?php

declare(strict_types=1);

namespace Tests\Fixtures\Command;

use NielsJanssen\Laravel\Discovery\Feature\Command\ConsoleCommand;

#[ConsoleCommand(
    name: 'fixture:invokable',
    description: 'Desc',
    aliases: ['fixture:inv'],
)]
class InvokableCommand
{
    public function __invoke(): void {}
}
