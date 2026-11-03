<?php

declare(strict_types=1);

namespace Tests\Fixtures\Command;

use NielsJanssen\Laravel\Discovery\Command\ConsoleArgument;
use NielsJanssen\Laravel\Discovery\Command\ConsoleCommand;
use NielsJanssen\Laravel\Discovery\Command\ConsoleOption;

class RenamedInputCommand
{
    /** @var array<string, mixed> */
    public static array $captured = [];

    #[ConsoleCommand(name: 'fixture:renamed-input')]
    public function run(
        #[ConsoleArgument(name: 'target')]
        string $originalName,
        #[ConsoleOption]
        bool $dryRun = false,
        #[ConsoleOption(name: 'batch')]
        string $batchSize = '10',
    ): void {
        self::$captured = compact('originalName', 'dryRun', 'batchSize');
    }
}
