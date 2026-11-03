<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Command;

use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command as LaravelCommand;
use Illuminate\Console\Parser;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\DevCommands;
use NielsJanssen\Laravel\Discovery\Command\Exception\InvalidCommandRegistrationException;
use Symfony\Component\Console\Attribute\AsCommand;
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
            $dev = $class->getAttribute(DevCommand::class);

            $this->discoveryItems->add($location, new DiscoveredCommand(
                reflector: $class,
                dev: $dev === null ? null : DiscoveredDevCommand::fromAttribute(
                    attribute: $dev,
                    artisanCommand: self::laravelCommandName($class),
                    file: $class->getFileName(),
                    line: (int) $class->getReflection()->getStartLine(),
                    class: $class->getName(),
                    function: $class->getReflection()->hasMethod('handle') ? 'handle' : '__invoke',
                ),
            ));
            return;
        }

        if ($class->hasAttribute(DevCommand::class)) {
            throw InvalidCommandRegistrationException::forCommand(
                $class->getName(),
                '#[DevCommand] on a class needs a class extending ' . LaravelCommand::class . '; put it on a #[ConsoleCommand] method instead.',
            );
        }

        foreach ($class->getPublicMethods() as $method) {
            $definition = $method->getAttribute(ConsoleCommand::class);
            $dev = $method->getAttribute(DevCommand::class);

            if ($definition === null) {
                if ($dev !== null) {
                    throw InvalidCommandRegistrationException::forCommand(
                        "{$class->getName()}::{$method->getName()}",
                        '#[DevCommand] needs #[ConsoleCommand] on the same method.',
                    );
                }

                continue;
            }

            $this->discoveryItems->add($location, new DiscoveredCommand(
                reflector: $method,
                definition: $definition,
                dev: $dev === null ? null : DiscoveredDevCommand::fromAttribute(
                    attribute: $dev,
                    artisanCommand: $definition->name,
                    file: (string) $method->getReflection()->getFileName(),
                    line: (int) $method->getReflection()->getStartLine(),
                    class: $class->getName(),
                    function: $method->getName(),
                ),
            ));
        }
    }

    public function apply(): void
    {
        $this->registerDevCommands();

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

    private function registerDevCommands(): void
    {
        if (! $this->app->runningInConsole() || ! class_exists(DevCommands::class)) {
            return;
        }

        $vendorPath = $this->app->basePath('vendor');

        /** @var DiscoveredCommand<Reflector> $command */
        foreach ($this->discoveryItems as $command) {
            if ($command->dev !== null) {
                DiscoveredDevCommands::add($command->dev->toLaravel($vendorPath));
            }
        }
    }

    /**
     * Resolve a Laravel command's name the way its constructor does, without instantiating it.
     *
     * @param ClassReflector<object> $class
     *
     * @throws InvalidCommandRegistrationException
     */
    private static function laravelCommandName(ClassReflector $class): string
    {
        $reflection = $class->getReflection();
        $defaults = $reflection->getDefaultProperties();

        $signature = ($reflection->getAttributes(Signature::class)[0] ?? null)?->newInstance()->signature
            ?? $defaults['signature']
            ?? null;

        $name = is_string($signature)
            ? Parser::parse($signature)[0]
            : $defaults['name'] ?? ($reflection->getAttributes(AsCommand::class)[0] ?? null)?->newInstance()->name;

        foreach (is_string($name) ? explode('|', $name) : [] as $segment) {
            if ($segment !== '') {
                return $segment;
            }
        }

        throw InvalidCommandRegistrationException::forCommand(
            $class->getName(),
            '#[DevCommand] needs the command to declare a name or signature.',
        );
    }
}
