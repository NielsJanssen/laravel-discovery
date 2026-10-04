<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredModelBinding;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\OmittableType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeKind;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRegistry;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use RuntimeException;
use UnitEnum;

/**
 * Builds #[Input] classes from their values, keyed by property name.
 *
 * @phpstan-type PropertyPlan array{property: ReflectionProperty, takesNull: bool, class: class-string|null, nullable: bool, of: class-string|null}
 * @phpstan-type ClassPlan array{reflection: ReflectionClass<object>, properties: array<string, PropertyPlan>, constructor: array<string, bool>}
 */
final class InputHydrator implements Hydrator
{
    /** @var array<class-string, bool> */
    private array $inputs = [];

    /** @var array<class-string, ClassPlan> */
    private array $plans = [];

    public function __construct(
        private readonly Container $container,
    ) {}

    public function hydrates(string $class): bool
    {
        return $this->inputs[$class] ??= Input::marks($class);
    }

    /**
     * @param  array<string, mixed>  $args  keyed by property name
     */
    public function hydrate(string $class, array $args): object
    {
        $plan = $this->plans[$class] ??= $this->plan($class);
        $values = [];

        foreach ($args as $name => $value) {
            $property = $plan['properties'][$name] ?? null;

            if ($property === null) {
                continue;
            }

            // An explicit null for a property that takes none leaves its default in place.
            if ($value === null && ! $property['takesNull']) {
                continue;
            }

            $values[$name] = $this->convert($property, $value);
        }

        $arguments = [];

        foreach ($plan['constructor'] as $name => $defaultsToNull) {
            if (array_key_exists($name, $values)) {
                $arguments[$name] = $values[$name];
                unset($values[$name]);
            } elseif ($defaultsToNull) {
                $arguments[$name] = null;
            }
        }

        $object = $plan['reflection']->newInstanceArgs($arguments);

        foreach ($values as $name => $value) {
            $plan['properties'][$name]['property']->setValue($object, $value);
        }

        return $object;
    }

    /**
     * @param  class-string  $class
     * @return ClassPlan
     */
    private function plan(string $class): array
    {
        $reflection = new ReflectionClass($class);
        $properties = [];

        foreach ($reflection->getProperties() as $property) {
            $type = $property->getType();
            $omittable = OmittableType::of($type);
            $inner = $omittable === null ? $type : $omittable->inner;
            $target = $inner instanceof ReflectionNamedType && class_exists($inner->getName()) ? $inner->getName() : null;
            $of = $target === null ? ($property->getAttributes(Field::class)[0] ?? null)?->newInstance()->of : null;

            $properties[$property->getName()] = [
                'property' => $property,
                'takesNull' => $type?->allowsNull() ?? true,
                'class' => $target,
                'nullable' => $omittable === null ? ($type?->allowsNull() ?? true) : $omittable->allowsNull,
                'of' => $of !== null && (class_exists($of) || enum_exists($of)) ? $of : null,
            ];
        }

        $constructor = [];

        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            $constructor[$parameter->getName()] = ! $parameter->isDefaultValueAvailable() && $parameter->allowsNull();
        }

        return ['reflection' => $reflection, 'properties' => $properties, 'constructor' => $constructor];
    }

    /**
     * @param  PropertyPlan  $property
     */
    private function convert(array $property, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($property['class'] !== null) {
            return $this->convertTo($property['class'], $value, $property['nullable']);
        }

        $of = $property['of'];

        if ($of === null || ! is_array($value)) {
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
            $query = DiscoveredModelBinding::lookup($class, $value);

            return $nullable ? $query->first() : $query->firstOrFail();
        }

        if (is_array($value) && $this->hydrates($class)) {
            return $this->hydrate($this->container->make(TypeRegistry::class)->effective($class, TypeKind::Input), array_filter($value, is_string(...), ARRAY_FILTER_USE_KEY));
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
