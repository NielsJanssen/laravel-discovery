<?php

declare(strict_types=1);

namespace Tests\Fixtures\Command;

use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use NielsJanssen\Laravel\Discovery\Command\DevCommand;

#[Signature('acme:attribute {--force}')]
#[DevCommand]
class DevSignatureAttributeCommand extends Command
{
    protected $signature = 'acme:ignored';

    public function handle(): void {}
}
