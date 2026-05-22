<?php

declare(strict_types=1);

namespace Workbench\App\Command;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Workbench\App\Test\RandomNumberGenerator;

#[AsCommand(name: 'app:laravel', description: 'A standard Laravel command')]
class LaravelCommand extends Command
{
    public function __invoke(RandomNumberGenerator $rng): int
    {
        $this->output->writeln(sprintf(
            'Hello standard Laravel command %d!',
            $rng->generate(),
        ));

        return 0;
    }
}
