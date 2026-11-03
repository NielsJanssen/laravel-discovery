<?php

declare(strict_types=1);

namespace Tests\Fixtures\Command;

use Illuminate\Console\Command;
use NielsJanssen\Laravel\Discovery\Command\DevCommand;

#[DevCommand(arguments: '--fresh')]
class DevSignatureCommand extends Command
{
    protected $signature = 'acme:reverb {--fresh}';

    public function handle(): void {}
}
