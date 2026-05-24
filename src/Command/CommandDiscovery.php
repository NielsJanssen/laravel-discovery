<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Command;

use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Command as LaravelCommand;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Foundation\Application;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Reflection\ClassReflector;
use NielsJanssen\Laravel\Discovery\Command\Exception\InvalidCommandRegistrationException;

#[Singleton]
final class CommandDiscovery implements Discovery
{
    use IsDiscovery;

    public function __construct(
        private readonly Application $app,
    ) {}

    /**
     * @throws InvalidCommandRegistrationException
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (! $class->isInstantiable()) {
            return;
        }

        if ($class->is(LaravelCommand::class)) {
            $this->discoveryItems->add($location, new DiscoveredCommand(
                reflector: $class,
            ));
            return;
        }

        foreach ($class->getPublicMethods() as $method) {
            if ($definition = $method->getAttribute(ConsoleCommand::class)) {
                $this->discoveryItems->add($location, new DiscoveredCommand(
                    reflector: $method,
                    definition: $definition,
                ));
            }
        }
    }

    public function apply(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        Artisan::starting(function (Artisan $artisan) {
            foreach ($this->discoveryItems as $command) {
                $artisan->addCommand(
                    $command->definition
                        ? new Command($this->app, $command)
                        : $this->app->make($command->reflector->getName()),
                );
            }
        });
    }
}
