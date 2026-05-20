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
use Tempest\Reflection\MethodReflector;

#[SkipDiscovery]
final class CommandDiscovery implements Discovery, Feature
{
    use IsDiscovery;

    public function __construct(
        private readonly CommandRegistry $store,
    ) {}

    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if ($class->is(LaravelCommand::class)
            || $class->hasAttribute(Command::class)) {
            $this->discoveryItems->add($location, $class);

            return;
        }

        foreach ($class->getPublicMethods() as $method) {
            if ($method->hasAttribute(Command::class)) {
                $this->discoveryItems->add($location, $method);
            }
        }
    }

    public function apply(): void
    {
        foreach ($this->discoveryItems as $reflector) {
            if ($reflector instanceof ClassReflector) {
                $this->store->commands[] = new CommandDefinition(
                    reflector: $reflector,
                    definition: $reflector->getAttribute(Command::class),
                );
            }

            if ($reflector instanceof MethodReflector) {
                foreach ($reflector->getAttributes(Command::class) as $definition) {
                    $this->store->commands[] = new CommandDefinition(
                        reflector: $reflector,
                        definition: $definition,
                    );
                }
            }
        }
    }

    public static function register(Application $app, DiscoveryConfig $config): void
    {
        $app->singleton(__CLASS__);
        $app->singleton(CommandRegistry::class);

        $app->afterResolving(Kernel::class, function (Kernel $kernel) use ($app) {
            $app->make(CommandRegistry::class)->register($kernel);
        });
    }
}
