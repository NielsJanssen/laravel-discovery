<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument;

use Illuminate\Database\Eloquent\Model;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\OmittableType;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use RuntimeException;
use UnitEnum;

/** Builds #[Input] classes from their values, keyed by property name. */
final class InputHydrator implements Hydrator
{
    /** @var array<class-string, bool> */
    private array $inputs = [];

    public function hydrates(string $class): bool
    {
        return $this->inputs[$class] ??= Input::marks($class);
    }

    /**
     * @param  array<string, mixed>  $args  keyed by property name
     */
    public function hydrate(string $class, array $args): object
    {
        $reflection = new ReflectionClass($class);
        $values = [];

        foreach ($args as $name => $value) {
            if (! $reflection->hasProperty($name)) {
                continue;
            }

            $property = $reflection->getProperty($name);

            // An explicit null for a property that takes none leaves its default in place.
            if ($value === null && ! ($property->getType()?->allowsNull() ?? true)) {
                continue;
            }

            $values[$name] = $this->convert($property, $value);
        }

        $arguments = [];

        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $values)) {
                $arguments[$name] = $values[$name];
                unset($values[$name]);
            } elseif (! $parameter->isDefaultValueAvailable() && $parameter->allowsNull()) {
                $arguments[$name] = null;
            }
        }

        $object = $reflection->newInstanceArgs($arguments);

        foreach ($values as $name => $value) {
            $reflection->getProperty($name)->setValue($object, $value);
        }

        return $object;
    }

    private function convert(ReflectionProperty $property, mixed $value): mixed
    {
        $type = $property->getType();
        $omittable = OmittableType::of($type);
        $nullable = $omittable === null ? ($type?->allowsNull() ?? true) : $omittable->allowsNull;
        $type = $omittable === null ? $type : $omittable->inner;

        if ($value === null) {
            return null;
        }

        if ($type instanceof ReflectionNamedType && class_exists($type->getName())) {
            return $this->convertTo($type->getName(), $value, $nullable);
        }

        $of = ($property->getAttributes(Field::class)[0] ?? null)?->newInstance()->of;

        if ($of === null || ! is_array($value) || ! (class_exists($of) || enum_exists($of))) {
            return $value;
        }

        return array_map(fn(mixed $item): mixed => $item === null ? null : $this->convertTo($of, $item, true), $value);
    }

    /**
     * @param  class-string  $class
     */
    private function convertTo(string $class, mixed $value, bool $nullable): mixed
    {
        if ($value instanceof $class) {
            return $value;
        }

        if (enum_exists($class) && is_string($value)) {
            return $this->enumCase($class, $value);
        }

        if (is_a($class, Model::class, true)) {
            $query = $class::query()->where(new $class()->getRouteKeyName(), $value);

            return $nullable ? $query->first() : $query->firstOrFail();
        }

        if (is_array($value) && $this->hydrates($class)) {
            return $this->hydrate($class, array_filter($value, is_string(...), ARRAY_FILTER_USE_KEY));
        }

        return $value;
    }

    /**
     * @param  class-string<UnitEnum>  $class
     */
    private function enumCase(string $class, string $name): UnitEnum
    {
        foreach ($class::cases() as $case) {
            if ($case->name === $name) {
                return $case;
            }
        }

        throw new RuntimeException("Cannot hydrate $class: it has no case named [$name].");
    }
}
