<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Command;

use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Command as LaravelCommand;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Foundation\Application;
use NielsJanssen\Laravel\Discovery\Command\Exception\InvalidCommandRegistrationException;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Reflection\ClassReflector;
use Tempest\Reflection\Reflector;

#[Singleton]
final class CommandDiscovery implements Discovery
{
    use IsDiscovery;

    public function __construct(
        private readonly Application $app,
    ) {}

    /**
     * @param ClassReflector<object> $class
     *
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
        Artisan::starting(function (Artisan $artisan) {
            /** @var DiscoveredCommand<Reflector> $command */
            foreach ($this->discoveryItems as $command) {
                /** @var Command|string $resolvedCommand */
                $resolvedCommand = $command->definition
                    ? new Command($this->app, $command)
                    : (is_subclass_of($commandClass = $command->reflector->getName(), LaravelCommand::class)
                        ? $commandClass
                        : $this->app->make($commandClass));

                if (! is_subclass_of($resolvedCommand, LaravelCommand::class)) {
                    throw new InvalidCommandRegistrationException(
                        sprintf(
                            'Discovered command "%s" is not a valid Laravel command.',
                            $command->reflector->getName(),
                        ),
                    );
                }

                $artisan->resolve($resolvedCommand);
            }
        });
    }
}
