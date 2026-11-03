<?php

declare(strict_types=1);

namespace Tests\Fixtures\Command;

use Illuminate\Console\Command;
use NielsJanssen\Laravel\Discovery\Command\DevCommand;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('|acme:hidden|acme:alias')]
#[DevCommand]
class DevAsCommand extends Command
{
    public function handle(): void {}
}
