<?php

declare(strict_types=1);

namespace Workbench\App\Domain\Test\Command;

use Illuminate\Console\OutputStyle;
use NielsJanssen\Laravel\Discovery\Feature\Command\ConsoleCommand;
use NielsJanssen\Laravel\Discovery\Feature\Command\Middleware\Caution;

readonly class DangerousCommand
{
    public function __construct(
        private OutputStyle $output,
    ) {}

    #[ConsoleCommand(name: 'dangerous', middleware: [Caution::class])]
    public function run(): int
    {
        $this->output->writeln('💥? Doing something dangerous!');

        return 0;
    }
}
