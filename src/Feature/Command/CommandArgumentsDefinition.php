<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Feature\Command;

use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Tempest\Reflection\MethodReflector;
use Tempest\Reflection\ParameterReflector;

readonly class CommandArgumentsDefinition
{
    public function __construct(
        /** @var list<InputArgument|InputOption> */
        private array $arguments = [],
    ) {}

    public static function from(MethodReflector $method): self
    {
        return new self(
            arguments: array_map(
                static::parseParameter(...),
                iterator_to_array($method->getParameters()),
            ),
        );
    }

    private static function parseParameter(ParameterReflector $parameter): InputArgument|InputOption
    {
        $attribute = $parameter->getAttribute(ConsoleArgument::class)
            ?? $parameter->getAttribute(ConsoleOption::class);

        $type = $parameter->getType();
        $default = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;

        if ($attribute instanceof ConsoleOption) {
            return new InputOption(
                name: Str::kebab($attribute->name ?? $parameter->getName()),
                shortcut: $attribute->aliases ?? [],
                mode: match (true) {
                    $type->getName() === 'bool' => InputOption::VALUE_NONE,
                    $parameter->isDefaultValueAvailable() || $default !== null => InputOption::VALUE_OPTIONAL,
                    default => InputOption::VALUE_REQUIRED,
                },
                description: $attribute->description ?? '',
                default: $type->getName() !== 'bool' ? $default : null,
            );
        }

        return new InputArgument(
            name: Str::kebab($attribute->name ?? $parameter->getName()),
            mode: ($type->getName() === 'array' ? InputArgument::IS_ARRAY : 0)
                | ($parameter->isDefaultValueAvailable() ? InputArgument::OPTIONAL : InputArgument::REQUIRED),
            description: $attribute->description ?? '',
            default: $default,
        );
    }

    public function resolveInput(InputInterface $input): array
    {
        $inputArguments = $input->getArguments();

        // The first argument is always the command name, which we can ignore.
        unset($inputArguments['command']);

        $inputValues = array_merge($inputArguments, $input->getOptions());

        return array_map(
            static fn(InputArgument|InputOption $argument) => $inputValues[$argument->getName()] ?? null,
            $this->arguments,
        );
    }

    public function define(InputDefinition $definition): void
    {
        foreach ($this->arguments as $argument) {
            if ($argument instanceof InputOption) {
                $definition->addOption($argument);
            } else {
                $definition->addArgument($argument);
            }
        }
    }
}
