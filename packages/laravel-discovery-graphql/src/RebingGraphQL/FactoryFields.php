<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Foundation\Application;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\Naming;
use RuntimeException;

/** Builds Rebing field definitions from the fields a TypeFactory or a TypeDefinition yields for a type. */
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
        $owner = new FactoryOwner(
            $type->name,
            "type factory $class",
            sprintf('type [%s] (%s)', $type->name, $type->class),
            sprintf('#[Type(naming:)] on %s', $type->class),
            $type->naming,
        );
        $context = new TypeContext($type->name, $type->class, $position, $this->names->fields($owner->naming, $owner->namingLabel), $declared);

        return $this->build($owner, $factory->fields($context), $declared, $position);
    }

    /**
     * @param  iterable<Field>  $fields
     * @param  list<string>  $declared  the field names the type already has
     * @return array<string, array<string, mixed>>
     */
    public function build(FactoryOwner $owner, iterable $fields, array $declared, Position $position): array
    {
        $definitions = [];

        foreach ($fields as $field) {
            $name = $field->name ?? throw new LogicException(sprintf('The %s yielded a field without a name for %s. Set name: on the Field.', $owner->origin, $owner->subject));

            if (in_array($name, $declared, true) || isset($definitions[$name])) {
                throw new LogicException(sprintf('The %s yields a field "%s" that %s already has. Rename the factory field, or drop one of the two.', $owner->origin, $name, $owner->subject));
            }

            $definitions[$name] = $this->definition($owner, $name, $field, $position);
        }

        return $definitions;
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(FactoryOwner $owner, string $name, Field $field, Position $position): array
    {
        $path = "{$owner->name}.$name";

        $args = $this->args($owner, $name, $field);
        $definition = [
            'type' => $this->registry->resolve($this->typeRef($owner, 'Field', $name, $field), $position),
            'resolve' => $this->resolver($path, $name, $field->resolve, array_map(static fn(array $arg): string => $arg['key'], $args)),
        ];

        if ($args !== []) {
            $definition['args'] = array_map(static fn(array $arg): array => $arg['definition'], $args);
        }

        return $this->described($definition, $field);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function described(array $definition, Field $field): array
    {
        if ($field->description !== null) {
            $definition['description'] = $field->description;
        }

        if ($field->deprecationReason !== null) {
            $definition['deprecationReason'] = $field->deprecationReason;
        }

        return $definition;
    }

    /**
     * @return array<string, array{key: string, definition: array<string, mixed>}> keyed by GraphQL arg name
     */
    private function args(FactoryOwner $owner, string $name, Field $field): array
    {
        $naming = $this->names->arguments($owner->naming, $owner->namingLabel);
        $args = [];

        foreach ($field->args as $key => $arg) {
            $argName = $this->names->name($naming, (string) $key, "Argument $key of field $name");
            $label = "$name($argName)";

            if (isset($args[$argName])) {
                throw new LogicException(sprintf('Field "%s" from the %s for type [%s] has two args named "%s". Rename one.', $name, $owner->origin, $owner->name, $argName));
            }

            if ($arg->hasRules()) {
                throw new LogicException(sprintf('Argument "%s" of field "%s" from the %s for type [%s] sets rules:, which are not applied to the args of a type field. Remove rules:.', $argName, $name, $owner->origin, $owner->name));
            }

            $definition = ['type' => $this->registry->resolve($this->typeRef($owner, 'Argument', $label, $arg), Position::Input)];
            $args[$argName] = ['key' => (string) $key, 'definition' => $this->described($definition, $arg)];
        }

        return $args;
    }

    private function typeRef(FactoryOwner $owner, string $kind, string $name, Field $field): TypeRef
    {
        if ($field->type !== null && $field->of !== null) {
            throw new LogicException(sprintf('%s "%s" from the %s for type [%s] sets both type: and of:. Use of: for a list of that type, or type: for a single value.', $kind, $name, $owner->origin, $owner->name));
        }

        $target = $field->of ?? $field->type ?? throw new LogicException(sprintf('%s "%s" from the %s for %s has no type. Set type:, or of: for a list.', $kind, $name, $owner->origin, $owner->subject));

        return TypeRef::from($target, $field->of !== null, $field->nullable, $field->nullableItems);
    }

    /**
     * @param  Closure(mixed, array<string, mixed>, mixed, ResolveInfo): mixed|null  $resolve
     * @param  array<string, string>  $argKeys  the key each GraphQL arg name has in the Field's args
     */
    private function resolver(string $path, string $name, ?Closure $resolve, array $argKeys): Closure
    {
        if ($resolve !== null) {
            return static function (mixed $root, array $args, mixed $context, ResolveInfo $info) use ($resolve, $argKeys): mixed {
                $keyed = [];

                foreach ($argKeys as $argName => $key) {
                    if (array_key_exists($argName, $args)) {
                        $keyed[$key] = $args[$argName];
                    }
                }

                return $resolve($root, $keyed, $context, $info);
            };
        }

        return static fn(mixed $root): mixed => match (true) {
            is_array($root) => $root[$name] ?? null,
            is_object($root) => $root->{$name} ?? null,
            default => throw new RuntimeException("Cannot resolve $path: expected an object or array, got " . get_debug_type($root) . '.'),
        };
    }
}
