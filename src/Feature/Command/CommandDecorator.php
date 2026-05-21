<?php

namespace NielsJanssen\Laravel\Discovery\Feature\Command;

use Illuminate\Console\Command as LaravelCommand;
use Illuminate\Container\Container;
use Tempest\Reflection\ClassReflector;
use Tempest\Reflection\MethodReflector;

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
            public function __construct(
                private readonly Container $container,
                private readonly CommandDefinition $definition,
            ) {
                $this->aliases     = $definition->definition->aliases;
                $this->description = $definition->definition->description;
                $this->name        = $definition->definition->name;
                $this->signature   = $definition->definition->signature;

                parent::__construct();
            }

            public function __invoke(): mixed
            {
                $reflector = $this->definition->reflector;

                if ($reflector instanceof ClassReflector) {
                    $command = $this->container->make($reflector->getName());
                } elseif ($reflector instanceof MethodReflector) {
                    $commandInstance = $this->container->make($reflector->getDeclaringClass()->getName());

                    $command = $reflector->getReflection()->getClosure($commandInstance);
                } else {
                    throw new \LogicException('Unsupported reflector type');
                }

                return $this->container->call($command, [
                    'output' => $this->output,
                    'input' => $this->input,
                    'command' => $this,
                ]);
            }
        };
    }
}
