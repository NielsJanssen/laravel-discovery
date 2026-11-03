<?php

declare(strict_types=1);

namespace Tests\Fixtures\Command;

use Illuminate\Console\Command;
use NielsJanssen\Laravel\Discovery\Command\DevCommand;

#[DevCommand]
class DevNamedCommand extends Command
{
    protected $name = 'acme:named';

    public function __invoke(): void {}
}
