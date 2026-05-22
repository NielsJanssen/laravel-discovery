<?php

declare(strict_types=1);

namespace Workbench\App\Command;

use Illuminate\Console\OutputStyle;
use NielsJanssen\Laravel\Discovery\Command\ConsoleCommand;
use NielsJanssen\Laravel\Discovery\Command\Middleware\Caution;

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
