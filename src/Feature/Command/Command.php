<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Feature\Command;

use Illuminate\Console\Command as LaravelCommand;
use Illuminate\Container\Container;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Tempest\Discovery\SkipDiscovery;
use Tempest\Reflection\ClassReflector;
use Tempest\Reflection\MethodReflector;

#[SkipDiscovery]
final class Command extends LaravelCommand
{
    private string $commandClass;

    private CommandArgumentsDefinition $commandArgumentsDefinition;

    private MethodReflector $commandMethodReflector;

    /** @var list<CommandMiddleware> */
    private array $resolvedMiddleware = [];

    public function __construct(
        private readonly Container         $container,
        private readonly DiscoveredCommand $definition,
    ) {
        $this->name = $definition->definition->name;

        parent::__construct();

        $this->setAliases($definition->definition->aliases);
        $this->setDescription($definition->definition->description ?? '');

        $reflector = $this->definition->reflector;

        if ($reflector instanceof ClassReflector) {
            $this->commandClass           = $reflector->getName();
            $this->commandMethodReflector = $reflector->getMethod('__invoke');
        } elseif ($reflector instanceof MethodReflector) {
            $this->commandClass           = $reflector->getDeclaringClass()->getName();
            $this->commandMethodReflector = $reflector;
        } else {
            throw new \LogicException('Unsupported reflector type');
        }

        $this->resolveCommandArguments();
    }

    public function __invoke(): mixed
    {
        $instance = $this->container->make($this->commandClass, $io = [
            'input'  => $this->input,
            'output' => $this->output,
        ]);

        $pipeline = function () use ($instance, $io) {
            return $this->container->call(
                $this->commandMethodReflector->getReflection()->getClosure($instance),
                array_merge($io, $this->commandArgumentsDefinition->resolveInput($this->input)),
            );
        };

        foreach (array_reverse($this->resolvedMiddleware) as $middleware) {
            $next = $pipeline;

            $pipeline = function () use ($middleware, $next) {
                return $middleware($this, $next);
            };
        }

        return $pipeline();
    }

    private function resolveCommandArguments(): void
    {
        $this->commandArgumentsDefinition = CommandArgumentsDefinition::from($this->commandMethodReflector);
        $this->commandArgumentsDefinition->define(
            $this->getDefinition(),
        );

        foreach ($this->definition->definition->middleware as $middleware) {
            $resolved = is_string($middleware)
                ? $this->container->make($middleware)
                : $middleware;

            $this->resolvedMiddleware[] = $resolved;

            if ($resolved instanceof ProvidesInputOptions) {
                foreach ($resolved->getOptions() as $option) {
                    match (true) {
                        $option instanceof InputOption   => $this->getDefinition()->addOption($option),
                        $option instanceof InputArgument => $this->getDefinition()->addArgument($option),
                    };
                }
            }
        }
    }
}
