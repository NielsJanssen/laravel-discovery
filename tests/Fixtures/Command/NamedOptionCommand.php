<?php

declare(strict_types=1);

namespace Tests\Fixtures\Command;

use NielsJanssen\Laravel\Discovery\Feature\Command\ConsoleCommand;
use NielsJanssen\Laravel\Discovery\Feature\Command\ConsoleOption;

class NamedOptionCommand
{
    #[ConsoleCommand(name: 'fixture:named-option')]
    public function run(
        #[ConsoleOption(name: 'custom-opt')]
        string $originalName = '',
    ): void {}
}
