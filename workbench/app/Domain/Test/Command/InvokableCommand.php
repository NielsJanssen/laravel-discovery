<?php

declare(strict_types=1);

namespace Workbench\App\Domain\Test\Command;

use Illuminate\Console\OutputStyle;
use NielsJanssen\Laravel\Discovery\Feature\Command\Command;
use Workbench\App\Domain\Test\RandomNumberGenerator;

#[Command(
    name: 'app:invokable',
    description: 'Invokable command',
    aliases: ['app:invokable-alias'],
)]
readonly class InvokableCommand
{
    public function __construct(
        private RandomNumberGenerator $rng,
    ) {}

    public function __invoke(OutputStyle $output): int
    {
        $output->writeln(sprintf(
            'Hello from invokable %d!',
            $this->rng->generate(),
        ));

        return 0;
    }
}
