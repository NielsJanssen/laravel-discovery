<?php

declare(strict_types=1);

namespace Tests\Fixtures\Command;

use NielsJanssen\Laravel\Discovery\Feature\Command\ConsoleCommand;

#[ConsoleCommand(name: 'fixture:abstract')]
abstract class AbstractCommand
{
    abstract public function __invoke(): void;
}
