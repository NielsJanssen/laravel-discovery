<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Feature\Command;

use Illuminate\Console\Command as LaravelCommand;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;
use NielsJanssen\Laravel\Discovery\Feature\Command\Exception\InvalidCommandRegistrationException;
use NielsJanssen\Laravel\Discovery\Feature\Feature;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryConfig;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Discovery\SkipDiscovery;
use Tempest\Reflection\ClassReflector;

#[Singleton]
#[SkipDiscovery]
final class CommandDiscovery implements Discovery, Feature
{
    use IsDiscovery;

    /** @var list<\NielsJanssen\Laravel\Discovery\Feature\Command\CommandDefinition> */
    public private(set) array $commands = [];

    /**
     * @throws \NielsJanssen\Laravel\Discovery\Feature\Command\Exception\InvalidCommandRegistrationException
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (! $class->isInstantiable()) {
            return;
        }

        if ($class->is(LaravelCommand::class)) {
            $this->discoveryItems->add($location, [$class, null]);
            return;
        }

        if ($definition = $class->getAttribute(Command::class)) {
            try {
                $class->getMethod('__invoke');
            } catch (\ReflectionException) {
                throw InvalidCommandRegistrationException::forCommand($class->getName(), 'Command classes must have an __invoke method');
            }

            $this->discoveryItems->add($location, [$class, $definition]);
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
        $app->afterResolving(Kernel::class, static function (Kernel $kernel, Application $app) {
            $decorator = $app->make(CommandDecorator::class);

            foreach ($app->make(self::class)->commands as $command) {
                $kernel->registerCommand(
                    $command->definition
                        ? $decorator->decorateCommand($command)
                        : $app->make($command->reflector->getName()),
                );
            }
        });
    }
}
