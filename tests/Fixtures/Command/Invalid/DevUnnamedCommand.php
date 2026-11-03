<?php

declare(strict_types=1);

namespace Tests\Fixtures\Command\Invalid;

use Illuminate\Console\Command;
use NielsJanssen\Laravel\Discovery\Command\DevCommand;

#[DevCommand]
class DevUnnamedCommand extends Command
{
    public function handle(): void {}
}
