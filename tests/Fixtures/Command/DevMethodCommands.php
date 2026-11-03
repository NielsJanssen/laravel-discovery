<?php

declare(strict_types=1);

namespace Tests\Fixtures\Command;

use Illuminate\Foundation\DevCommandColor;
use NielsJanssen\Laravel\Discovery\Command\ConsoleCommand;
use NielsJanssen\Laravel\Discovery\Command\DevCommand;

class DevMethodCommands
{
    #[ConsoleCommand('acme:watch')]
    #[DevCommand]
    public function watch(): void {}

    #[ConsoleCommand('acme:sync')]
    #[DevCommand(name: 'sync', arguments: '--tries=1 --timeout=0', color: DevCommandColor::GREEN)]
    public function sync(): void {}

    #[ConsoleCommand('acme:listen')]
    #[DevCommand(arguments: '--queue=default')]
    public function listen(): void {}

    #[ConsoleCommand('acme:once')]
    public function once(): void {}
}
