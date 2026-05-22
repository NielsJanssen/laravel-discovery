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

    /**
     * @throws \NielsJanssen\Laravel\Discovery\Feature\Command\Exception\InvalidCommandRegistrationException
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

        if ($definition = $class->getAttribute(ConsoleCommand::class)) {
            try {
                $class->getMethod('__invoke');
            } catch (\ReflectionException) {
                throw InvalidCommandRegistrationException::forCommand($class->getName(), 'Command classes must have an __invoke method');
            }

            $this->discoveryItems->add($location, new DiscoveredCommand(
                reflector: $class,
                definition: $definition,
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

    public function apply(): void {}

    public static function register(Application $app, DiscoveryConfig $config): void
    {
        $app->afterResolving(Kernel::class, static function (Kernel $kernel, Application $app) {
            $decorator = $app->make(CommandDecorator::class);

            /** @var \NielsJanssen\Laravel\Discovery\Feature\Command\DiscoveredCommand $command */
            foreach ($app->make(self::class)->discoveryItems as $command) {
                $kernel->registerCommand(
                    $command->definition
                        ? $decorator->decorateCommand($command)
                        : $app->make($command->reflector->getName()),
                );
            }
        });
    }
}
