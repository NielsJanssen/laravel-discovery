<?php

declare(strict_types=1);

namespace Workbench\App\Domain\Test\Command;

use Illuminate\Console\OutputStyle;
use NielsJanssen\Laravel\Discovery\Feature\Command\ConsoleCommand;
use NielsJanssen\Laravel\Discovery\Feature\Command\ConsoleOption;
use NielsJanssen\Laravel\Discovery\Feature\Command\Middleware\Transaction;
use Workbench\App\Domain\Test\RandomNumberGenerator;

readonly class WorkCommand
{
    public function __construct(
        private RandomNumberGenerator $rng,
        private OutputStyle $output,
    ) {}

    #[ConsoleCommand(name: 'app:work', description: 'Does some work')]
    public function work(
        int $min = 1,
        int $max = 100,
        array $args = [],
        #[ConsoleOption(description: 'Whether to work hard')]
        bool $hard = false,
    ): int {
        $this->output->writeln(sprintf(
            'Hello world %d!',
            $this->rng->generate($min, $max),
        ));

        if ($hard) {
            $this->output->writeln('🥵');
        }

        dump($args);

        return 0;
    }

    #[ConsoleCommand(name: 'app:more-work', middleware: [Transaction::class])]
    public function moreWork(string ...$args): int
    {
        $this->output->writeln(sprintf(
            'Working harder %d!',
            $this->rng->generate(),
        ));

        dump($args);

        return 0;
    }
}
