<?php

declare(strict_types=1);

namespace Workbench\App\Command;

use Illuminate\Console\OutputStyle;
use NielsJanssen\Laravel\Discovery\Feature\Command\ConsoleCommand;
use Workbench\App\Test\RandomNumberGenerator;

#[ConsoleCommand(
    name: 'app:invokable',
    description: 'Invokable command',
    aliases: ['app:invokable-alias'],
)]
readonly class InvokableCommand
{
    public function __construct(
        private RandomNumberGenerator $rng,
        private OutputStyle $output,
    ) {}

    public function __invoke(): int
    {
        $this->output->writeln(sprintf(
            'Hello from invokable %d!',
            $this->rng->generate(),
        ));

        return 0;
    }
}
