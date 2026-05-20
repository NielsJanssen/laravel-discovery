<?php

namespace NielsJanssen\Laravel\Discovery\Feature\Command;

use Illuminate\Console\Command as LaravelCommand;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Tempest\Reflection\ClassReflector;
use Tempest\Reflection\MethodReflector;

class CommandRegistry
{
    public function __construct(
        private readonly Container $container,

        /** @var list<\NielsJanssen\Laravel\Discovery\Feature\Command\CommandDefinition> */
        public array $commands = [],
    ) {}

    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function register(Kernel $kernel): void
    {
        foreach ($this->commands as $command) {
            $kernel->registerCommand(
                match (true) {
                    $command->definition === null  => $this->container->make($command->reflector->getName()),
                    default => $this->createCommand($command),
                },
            );
        }
    }

    /**
     * Create a simple wrapper command to register with the kernel, which will resolve the actual
     * command from the container and call it.
     */
    private function createCommand(CommandDefinition $command): LaravelCommand
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
