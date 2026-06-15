<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Command;

use Illuminate\Console\Command as LaravelCommand;
use Illuminate\Container\Container;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Tempest\Discovery\SkipDiscovery;
use Tempest\Reflection\ClassReflector;
use Tempest\Reflection\MethodReflector;
use Tempest\Reflection\Reflector;

#[SkipDiscovery]
final class Command extends LaravelCommand
{
    /** @var class-string */
    private string $commandClass;

    private CommandArgumentsDefinition $commandArgumentsDefinition;

    private MethodReflector $commandMethodReflector;

    private readonly ConsoleCommand $consoleCommand;

    /** @var list<CommandMiddleware> */
    private array $resolvedMiddleware = [];

    /**
     * @param DiscoveredCommand<Reflector> $definition
     */
    public function __construct(
        private readonly Container         $container,
        private readonly DiscoveredCommand $definition,
    ) {
        $this->consoleCommand = $definition->definition
            ?? throw new \LogicException('Command requires a ConsoleCommand definition.');

        $this->name = $this->consoleCommand->name;

        parent::__construct();

        $this->setAliases($this->consoleCommand->aliases);
        $this->setDescription($this->consoleCommand->description ?? '');

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
        /** @var object $instance */
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

        foreach ($this->consoleCommand->middleware as $middleware) {
            $resolved = is_string($middleware)
                ? $this->container->make($middleware)
                : $middleware;

            if (!$resolved instanceof CommandMiddleware) {
                throw new \LogicException(sprintf(
                    'Middleware must implement %s, got %s',
                    CommandMiddleware::class,
                    is_object($resolved) ? get_class($resolved) : gettype($resolved),
                ));
            }

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
