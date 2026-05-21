<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Feature\Command;

use Illuminate\Console\Command as LaravelCommand;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;
use NielsJanssen\Laravel\Discovery\Feature\Feature;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryConfig;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Discovery\SkipDiscovery;
use Tempest\Reflection\ClassReflector;

#[SkipDiscovery]
final class CommandDiscovery implements Discovery, Feature
{
    use IsDiscovery;

    /** @var list<\NielsJanssen\Laravel\Discovery\Feature\Command\CommandDefinition> */
    private(set) array $commands = [];

    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if ($class->is(LaravelCommand::class)
            || ($definition = $class->getAttribute(Command::class))) {
            $this->discoveryItems->add($location, [$class, $definition ?? null]);

            return;
        }

        foreach ($class->getPublicMethods() as $method) {
            if ($definition = $method->getAttribute(Command::class)) {
                $this->discoveryItems->add($location, [$method, $definition]);
            }
        }
    }

    public function apply(): void
    {
        foreach ($this->discoveryItems as [$reflector, $definition]) {
            $this->commands[] = new CommandDefinition(
                reflector: $reflector,
                definition: $definition,
            );
        }
    }

    public static function register(Application $app, DiscoveryConfig $config): void
    {
        $app->singleton(__CLASS__);
        $app->singleton(CommandDecorator::class);

        $app->afterResolving(Kernel::class, static function (Kernel $kernel, Application $app) {
            $decorator = $app->make(CommandDecorator::class);

            foreach ($app->make(__CLASS__)->commands as $command) {
                $kernel->registerCommand(
                    $command->definition
                        ? $decorator->decorateCommand($command)
                        : $app->make($command->reflector->getName()),
                );
            }
        });
    }
}
