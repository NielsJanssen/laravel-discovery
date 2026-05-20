<?php

namespace Workbench\App\Domain\Test\Command;

use Illuminate\Console\OutputStyle;
use NielsJanssen\Laravel\Discovery\Feature\Command\Command;
use Workbench\App\Domain\Test\RandomNumberGenerator;

readonly class WorkCommand
{
    public function __construct(
        private RandomNumberGenerator $rng,
    ) {}

    #[Command(name: 'app:work', description: 'Does some work')]
    public function work(OutputStyle $output): int
    {
        $output->writeln(sprintf(
            'Hello world %d!',
            $this->rng->generate(),
        ));

        return 0;
    }

    #[Command(name: 'app:more-work')]
    public function moreWork(OutputStyle $output): int
    {
        $output->writeln(sprintf(
            'Working harder %d!',
            $this->rng->generate(),
        ));

        return 0;
    }
}
