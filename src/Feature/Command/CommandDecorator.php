<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Feature\Command;

use Illuminate\Console\Command as LaravelCommand;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Container\Container;

#[Singleton]
readonly class CommandDecorator
{
    public function __construct(
        private Container $container,
    ) {}

    /**
     * Create a simple wrapper command to register with the kernel, which will resolve the actual
     * command from the container and call it.
     */
    public function decorateCommand(DiscoveredCommand $command): LaravelCommand
    {
        return new DecoratedCommand($this->container, $command);
    }
}
