<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Feature\Command;

use Illuminate\Console\Command as LaravelCommand;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Container\Container;
use Tempest\Reflection\ClassReflector;
use Tempest\Reflection\MethodReflector;

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
    public function decorateCommand(CommandDefinition $command): LaravelCommand
    {
        return new class ($this->container, $command) extends LaravelCommand {
            private string $commandClass;

            private CommandArgumentsDefinition $commandSignature;

            private MethodReflector $commandMethodReflector;

            public function __construct(
                private readonly Container $container,
                private readonly CommandDefinition $definition,
            ) {
                $this->name = $definition->definition->name;

                parent::__construct();

                $this->setAliases($definition->definition->aliases);
                $this->setDescription($definition->definition->description ?? '');

                $reflector = $this->definition->reflector;

                if ($reflector instanceof ClassReflector) {
                    $this->commandClass = $reflector->getName();
                    $this->commandMethodReflector = $reflector->getMethod('__invoke');
                } elseif ($reflector instanceof MethodReflector) {
                    $this->commandClass = $reflector->getDeclaringClass()->getName();
                    $this->commandMethodReflector = $reflector;
                } else {
                    throw new \LogicException('Unsupported reflector type');
                }

                $this->commandSignature = CommandArgumentsDefinition::from($this->commandMethodReflector);
                $this->commandSignature->define(
                    $this->getDefinition(),
                );
            }

            public function __invoke(): mixed
            {
                $instance = $this->container->make($this->commandClass, [
                    'input' => $this->input,
                    'output' => $this->output,
                ]);

                return $this->commandMethodReflector->getReflection()
                    ->getClosure($instance)
                    ->call($instance, ...$this->commandSignature->resolveInput($this->input));
            }
        };
    }
}
