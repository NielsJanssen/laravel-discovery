<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Command;

use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\Exceptions\InvalidFormatException;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Console\CommandInput;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use LogicException;
use ReflectionEnum;
use ReflectionNamedType;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Tempest\Reflection\MethodReflector;
use Tempest\Reflection\ParameterReflector;
use UnitEnum;

readonly class CommandArgumentsDefinition
{
    public function __construct(
        /** @var list<InputParameter> */
        private array $parameters = [],
    ) {}

    public static function from(MethodReflector $method): self
    {
        return new self(
            parameters: array_values(array_filter(array_map(
                static::parseParameter(...),
                iterator_to_array($method->getParameters()),
            ))),
        );
    }

    private static function parseParameter(ParameterReflector $parameter): ?InputParameter
    {
        $attribute = $parameter->getAttribute(ConsoleArgument::class)
            ?? $parameter->getAttribute(ConsoleOption::class);

        $type = $parameter->getType();
        $class = $attribute !== null ? self::valueClass($parameter) : null;

        if ($class === null && !$type->isScalar() && $type->getName() !== 'array') {
            return null;
        }

        $enum = $class !== null && is_a($class, BackedEnum::class, true) ? $class : null;
        $date = $class !== null && is_a($class, DateTimeInterface::class, true) ? $class : null;

        if ($attribute?->format !== null && $date === null) {
            throw new LogicException(sprintf(
                'The format of the parameter $%s in %s::%s only applies to a date.',
                $parameter->getName(),
                $parameter->getReflection()->getDeclaringClass()?->getName(),
                $parameter->getReflection()->getDeclaringFunction()->getName(),
            ));
        }

        $hasDefault = $parameter->isDefaultValueAvailable();
        $default = $hasDefault ? $parameter->getDefaultValue() : null;
        $optional = $hasDefault || ($class !== null && $parameter->getReflection()->allowsNull());

        $inputDefault = match (true) {
            $default instanceof BackedEnum => $default->value,
            is_object($default) => null,
            default => $default,
        };

        $suggestedValues = $enum !== null
            ? array_map(static fn(BackedEnum $case): string => (string) $case->value, $enum::cases())
            : [];

        if ($attribute instanceof ConsoleOption) {
            $input = new InputOption(
                name: Str::kebab($attribute->name ?? $parameter->getName()),
                shortcut: $attribute->shortcut ?? [],
                mode: match (true) {
                    $class === null && $type->getName() === 'bool' => InputOption::VALUE_NONE,
                    $hasDefault || $default !== null => InputOption::VALUE_OPTIONAL,
                    default => InputOption::VALUE_REQUIRED,
                },
                description: $attribute->description ?? '',
                default: $type->getName() !== 'bool' ? $inputDefault : null,
                suggestedValues: $suggestedValues,
            );
        } else {
            $input = new InputArgument(
                name: Str::kebab($attribute->name ?? $parameter->getName()),
                mode: ($type->getName() === 'array' ? InputArgument::IS_ARRAY : 0)
                    | ($optional ? InputArgument::OPTIONAL : InputArgument::REQUIRED),
                description: $attribute->description ?? '',
                default: $inputDefault,
                suggestedValues: $suggestedValues,
            );
        }

        return new InputParameter(
            parameterName: $parameter->getName(),
            input: $input,
            enum: $enum,
            date: $date,
            format: $attribute?->format,
            nullable: $parameter->getReflection()->allowsNull(),
            hasDefault: $hasDefault,
            default: $default,
        );
    }

    /**
     * @return class-string<BackedEnum|DateTimeInterface>|null
     */
    private static function valueClass(ParameterReflector $parameter): ?string
    {
        $type = $parameter->getReflection()->getType();

        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        $name = $type->getName();

        if (is_a($name, BackedEnum::class, true)) {
            return $name;
        }

        if (is_a($name, UnitEnum::class, true)) {
            throw new LogicException(sprintf(
                'The parameter $%s in %s::%s is typed as the enum %s, which has no backing values to read from the console.',
                $parameter->getName(),
                $parameter->getReflection()->getDeclaringClass()?->getName(),
                $parameter->getReflection()->getDeclaringFunction()->getName(),
                $name,
            ));
        }

        if (in_array($name, [DateTimeInterface::class, DateTime::class, DateTimeImmutable::class], true)
            || is_a($name, CarbonInterface::class, true)) {
            return $name;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveInput(InputInterface $input): array
    {
        $arguments = $input->getArguments();

        // The first argument is always the command name, which we can ignore.
        unset($arguments['command']);

        $commandInput = new CommandInput($arguments, $input->getOptions());
        $values = $commandInput->all();

        $map = [];

        foreach ($this->parameters as $parameter) {
            $map[$parameter->parameterName] = match (true) {
                $parameter->enum !== null => $this->resolveEnum($commandInput, $parameter, $parameter->enum),
                $parameter->date !== null => $this->resolveDate($commandInput, $parameter, $parameter->date),
                default => $values[$parameter->inputName()] ?? null,
            };
        }

        return $map;
    }

    /**
     * @param class-string<BackedEnum> $enum
     */
    private function resolveEnum(CommandInput $input, InputParameter $parameter, string $enum): ?BackedEnum
    {
        $key = $parameter->inputName();

        if ($input->isNotFilled($key)) {
            $default = $this->fallback($parameter);

            return $default instanceof BackedEnum ? $default : null;
        }

        $cases = array_map(static fn(BackedEnum $case): string => (string) $case->value, $enum::cases());
        $value = $input->all()[$key];

        // An int-backed enum's tryFrom() throws a TypeError on a non-numeric string.
        $isIntBacked = (string) new ReflectionEnum($enum)->getBackingType() === 'int';

        $case = $isIntBacked && filter_var($value, FILTER_VALIDATE_INT) === false
            ? null
            : $input->enum($key, $enum);

        return $case ?? throw $this->invalid($parameter, sprintf(
            'does not accept "%s"; expected one of: %s.',
            $this->stringify($value),
            implode(', ', $cases),
        ));
    }

    /**
     * @param class-string<DateTimeInterface> $class
     */
    private function resolveDate(CommandInput $input, InputParameter $parameter, string $class): ?DateTimeInterface
    {
        $key = $parameter->inputName();

        if ($input->isNotFilled($key)) {
            $default = $this->fallback($parameter);

            return $default instanceof DateTimeInterface ? $default : null;
        }

        try {
            $date = $input->date($key, $parameter->format);
        } catch (InvalidFormatException) {
            $date = null;
        }

        if (!$date instanceof CarbonInterface) {
            throw $this->invalid($parameter, sprintf(
                'does not accept "%s"; expected a date%s.',
                $this->stringify($input->all()[$key]),
                $parameter->format !== null ? sprintf(' in the format "%s"', $parameter->format) : '',
            ));
        }

        if ($date instanceof $class) {
            return $date;
        }

        return match (true) {
            is_a($class, CarbonInterface::class, true) => $class::instance($date),
            $class === DateTimeImmutable::class => CarbonImmutable::instance($date),
            default => Carbon::instance($date),
        };
    }

    private function fallback(InputParameter $parameter): mixed
    {
        return match (true) {
            $parameter->hasDefault => $parameter->default,
            $parameter->nullable => null,
            default => throw $this->invalid($parameter, 'requires a value.'),
        };
    }

    private function invalid(InputParameter $parameter, string $problem): InvalidArgumentException|InvalidOptionException
    {
        return $parameter->input instanceof InputOption
            ? new InvalidOptionException(sprintf('The "--%s" option %s', $parameter->inputName(), $problem))
            : new InvalidArgumentException(sprintf('The "%s" argument %s', $parameter->inputName(), $problem));
    }

    private function stringify(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : get_debug_type($value);
    }

    public function define(InputDefinition $definition): void
    {
        foreach ($this->parameters as $parameter) {
            if ($parameter->input instanceof InputOption) {
                $definition->addOption($parameter->input);
            } else {
                $definition->addArgument($parameter->input);
            }
        }
    }
}
