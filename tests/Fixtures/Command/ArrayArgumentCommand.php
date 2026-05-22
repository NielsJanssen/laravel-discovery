<?php

declare(strict_types=1);

namespace Tests\Fixtures\Command;

use NielsJanssen\Laravel\Discovery\Feature\Command\ConsoleCommand;

class ArrayArgumentCommand
{
    #[ConsoleCommand(name: 'fixture:array-argument')]
    public function run(array $items = []): void {}
}
