<?php

declare(strict_types=1);

namespace Tests\Fixtures\Command;

use NielsJanssen\Laravel\Discovery\Command\ConsoleCommand;

#[ConsoleCommand(name: 'fixture:invalid')]
class InvalidCommand
{
    // No __invoke method — should trigger InvalidCommandRegistrationException
}
