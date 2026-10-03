<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Closure;
use Illuminate\Foundation\Application;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\Naming;
use RuntimeException;

/** Builds Rebing field definitions from the fields a TypeFactory yields for a type. */
final readonly class FactoryFields
{
    public function __construct(
        private Application $app,
        private Naming $names,
        private TypeRegistry $registry,
    ) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function definitions(DiscoveredType $type, Position $position): array
    {
        $class = (string) $type->factory;
        $factory = $this->app->make($class);

        if (! $factory instanceof TypeFactory) {
            throw new LogicException(sprintf('The type factory %s of %s resolves to %s, which is no %s.', $class, $type->class, get_debug_type($factory), TypeFactory::class));
        }

        $declared = array_column($type->fields, 'name');
        $context = new TypeContext(
            $type->name,
            $type->class,
            $position,
            $this->names->fields($type->naming, sprintf('#[Type(naming:)] on %s', $type->class)),
            $declared,
        );
        $definitions = [];

        foreach ($factory->fields($context) as $field) {
            $name = $field->name ?? throw new LogicException(sprintf('The type factory %s yielded a field without a name for type [%s] (%s). Set name: on the Field.', $class, $type->name, $type->class));

            if (in_array($name, $declared, true) || isset($definitions[$name])) {
                throw new LogicException(sprintf('The type factory %s yields a field "%s" that type [%s] (%s) already has. Rename the factory field, or drop one of the two.', $class, $name, $type->name, $type->class));
            }

            $definitions[$name] = $this->definition($type, $class, $name, $field, $position);
        }

        return $definitions;
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(DiscoveredType $type, string $factory, string $name, Field $field, Position $position): array
    {
        $definition = [
            'type' => $this->registry->resolve($this->typeRef($type, $factory, $name, $field), $position),
            'resolve' => $this->resolver("{$type->name}.$name", $name),
        ];

        if ($field->description !== null) {
            $definition['description'] = $field->description;
        }

        if ($field->deprecationReason !== null) {
            $definition['deprecationReason'] = $field->deprecationReason;
        }

        return $definition;
    }

    private function typeRef(DiscoveredType $type, string $factory, string $name, Field $field): TypeRef
    {
        if ($field->type !== null && $field->of !== null) {
            throw new LogicException(sprintf('Field "%s" from the type factory %s for type [%s] sets both type: and of:. Use of: for a list of that type, or type: for a single value.', $name, $factory, $type->name));
        }

        $target = $field->of ?? $field->type ?? throw new LogicException(sprintf('Field "%s" from the type factory %s for type [%s] (%s) has no type. Set type:, or of: for a list.', $name, $factory, $type->name, $type->class));

        return TypeRef::from($target, $field->of !== null, $field->nullable, $field->nullableItems);
    }

    private function resolver(string $path, string $name): Closure
    {
        return static fn(mixed $root): mixed => match (true) {
            is_array($root) => $root[$name] ?? null,
            is_object($root) => $root->{$name} ?? null,
            default => throw new RuntimeException("Cannot resolve $path: expected an object or array, got " . get_debug_type($root) . '.'),
        };
    }
}
