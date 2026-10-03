<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use LogicException;

/** Reads the naming strategies in `discovery.graphql.naming` and applies them to PHP member names. */
final class Naming
{
    public const string CONFIG = 'discovery.graphql.naming';

    private const array KEYS = ['fields', 'arguments', 'operations'];

    /** @var array<string, NamingStrategy>|null keyed by fields, arguments and operations */
    private ?array $configured = null;

    /** @var array<class-string<NamingStrategy>, NamingStrategy> */
    private array $instances = [];

    public function __construct(
        private readonly Repository $config,
        private readonly Application $container,
    ) {}

    /**
     * @param  string  $source  the attribute that sets $override, for the error message
     */
    public function fields(FieldCase|string|null $override = null, string $source = ''): NamingStrategy
    {
        return $override === null ? $this->configured()['fields'] : $this->strategy($override, $source);
    }

    /**
     * @param  string  $source  the attribute that sets $override, for the error message
     */
    public function arguments(FieldCase|string|null $override = null, string $source = ''): NamingStrategy
    {
        return $override === null ? $this->configured()['arguments'] : $this->strategy($override, $source);
    }

    public function operations(): NamingStrategy
    {
        return $this->configured()['operations'];
    }

    /**
     * @param  string  $member  the PHP member the name is for, for the error message
     */
    public function name(NamingStrategy $strategy, string $phpName, string $member): string
    {
        $name = $strategy->name($phpName);

        if (preg_match('/^[_A-Za-z][_0-9A-Za-z]*$/', $name) !== 1) {
            throw new LogicException(sprintf(
                'The naming strategy %s turns %s into "%s", which is not a valid GraphQL name. Return letters, digits and underscores only, not starting with a digit, or give the member an explicit name:.',
                $strategy instanceof FieldCase ? FieldCase::class . '::' . $strategy->name : $strategy::class,
                lcfirst($member),
                $name,
            ));
        }

        return $name;
    }

    /**
     * @return array<string, NamingStrategy>
     */
    private function configured(): array
    {
        if ($this->configured !== null) {
            return $this->configured;
        }

        $naming = $this->config->get(self::CONFIG, []);

        if (! is_array($naming)) {
            throw new LogicException(sprintf(
                "Config %s maps fields, arguments and operations to a naming strategy, as in ['fields' => FieldCase::Snake], got %s.",
                self::CONFIG,
                get_debug_type($naming),
            ));
        }

        $unknown = array_diff(array_map(strval(...), array_keys($naming)), self::KEYS);

        if ($unknown !== []) {
            throw new LogicException(sprintf(
                'Config %s has the unknown key [%s]. Use fields, arguments or operations.',
                self::CONFIG,
                implode(', ', $unknown),
            ));
        }

        $configured = [];

        foreach (self::KEYS as $key) {
            $configured[$key] = $this->strategy($naming[$key] ?? FieldCase::Preserve, sprintf('Config %s.%s', self::CONFIG, $key));
        }

        return $this->configured = $configured;
    }

    private function strategy(mixed $value, string $source): NamingStrategy
    {
        if ($value instanceof FieldCase) {
            return $value;
        }

        if (is_string($value) && FieldCase::tryFrom($value) !== null) {
            return FieldCase::from($value);
        }

        if (is_string($value) && is_a($value, NamingStrategy::class, true)) {
            return $this->instances[$value] ??= $this->instance($value, $source);
        }

        throw new LogicException(sprintf(
            "%s takes a FieldCase case (Preserve, Camel, Snake), its value ('preserve', 'camel', 'snake') or a class-string of a %s, got %s.",
            $source,
            NamingStrategy::class,
            is_string($value) ? "'$value'" : get_debug_type($value),
        ));
    }

    /**
     * Resolves a strategy class from the container, which may bind it to something else.
     *
     * @param  class-string<NamingStrategy>  $class
     */
    private function instance(string $class, string $source): NamingStrategy
    {
        $strategy = $this->container->make($class);

        return $strategy instanceof NamingStrategy
            ? $strategy
            : throw new LogicException(sprintf('%s names %s, but the container resolves it to %s, which is no %s.', $source, $class, get_debug_type($strategy), NamingStrategy::class));
    }
}
