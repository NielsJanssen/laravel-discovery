<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Closure;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\Type as GraphQLType;

/** Finds the discovered input objects inside a request value, at every depth and list index. */
final readonly class InputObjects
{
    public function __construct(
        private TypeRegistry $registry,
    ) {}

    /**
     * Every input object in the args a field definition declares, with its values and its validation path.
     *
     * @param  array<string, mixed>  $definitions  a field's args(), keyed by arg name
     * @param  array<string, mixed>  $args  the request args, keyed by arg name
     * @return iterable<array{0: DiscoveredType, 1: array<array-key, mixed>, 2: string}>
     */
    public function inArgs(array $definitions, array $args): iterable
    {
        foreach ($definitions as $name => $definition) {
            $type = is_array($definition) ? ($definition['type'] ?? null) : null;
            $type = $type instanceof Closure ? $type() : $type;

            if ($type instanceof GraphQLType && array_key_exists($name, $args)) {
                yield from $this->in($type, $args[$name], $name);
            }
        }
    }

    /**
     * Every discovered input object the args a field definition declares can hold, at any depth.
     *
     * @param  array<string, mixed>  $definitions  a field's args(), keyed by arg name
     * @return list<DiscoveredType>
     */
    public function declaredIn(array $definitions): array
    {
        $pending = array_map(static fn(mixed $definition): mixed => is_array($definition) ? ($definition['type'] ?? null) : null, array_values($definitions));
        $found = [];

        while ($pending !== []) {
            $type = array_pop($pending);
            $type = $type instanceof Closure ? $type() : $type;

            if (! $type instanceof GraphQLType) {
                continue;
            }

            $type = GraphQLType::getNamedType($type);

            if (! $type instanceof InputObjectType || isset($found[$type->name])) {
                continue;
            }

            $found[$type->name] = $this->registry->typeNamed($type->name);

            foreach ($type->getFields() as $field) {
                $pending[] = $field->getType();
            }
        }

        return array_values(array_filter($found, static fn(?DiscoveredType $discovered): bool => $discovered !== null && $discovered->kind === TypeKind::Input));
    }

    /**
     * @return iterable<array{0: DiscoveredType, 1: array<array-key, mixed>, 2: string}>
     */
    public function in(GraphQLType $type, mixed $value, string $path): iterable
    {
        if ($type instanceof NonNull) {
            $type = $type->getWrappedType();
        }

        if ($value === null) {
            return;
        }

        if ($type instanceof ListOfType) {
            foreach (is_array($value) ? $value : [$value] as $index => $item) {
                yield from $this->in($type->getWrappedType(), $item, "$path.$index");
            }

            return;
        }

        if (! $type instanceof InputObjectType || ! is_array($value)) {
            return;
        }

        $discovered = $this->registry->typeNamed($type->name);

        if ($discovered !== null && $discovered->kind === TypeKind::Input) {
            yield [$discovered, $value, $path];
        }

        foreach ($type->getFields() as $name => $field) {
            if (array_key_exists($name, $value)) {
                yield from $this->in($field->getType(), $value[$name], "$path.$name");
            }
        }
    }
}
