<?php

declare(strict_types=1);

namespace Tests\Fixtures\Command;

use NielsJanssen\Laravel\Discovery\Feature\Command\ConsoleCommand;

class ArgumentCommand
{
    #[ConsoleCommand(name: 'fixture:argument')]
    public function run(string $name, int $count = 1): void {}
}
