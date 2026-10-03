<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Foundation\Application;
use LogicException;
use Rebing\GraphQL\Support\Type as RebingType;
use RuntimeException;

/** A Rebing object type built from a DiscoveredType. */
final class DiscoveredObjectType extends RebingType
{
    public function __construct(
        private readonly Application $app,
        private readonly DiscoveredType $discoveredType,
    ) {}

    public function attributes(): array
    {
        $attributes = ['name' => $this->discoveredType->name];

        if ($this->discoveredType->description !== null) {
            $attributes['description'] = $this->discoveredType->description;
        }

        return $attributes;
    }

    public function fields(): array
    {
        $fields = [];

        foreach ($this->discoveredType->fields as $field) {
            $fields[$field->name] = $this->fieldDefinition($field);
        }

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldDefinition(DiscoveredTypeField $field): array
    {
        $registry = $this->app->make(TypeRegistry::class);
        $blueprint = new FieldBlueprint($this->app, $this->discoveredType, $field, $field->type, $this->resolver($field));

        foreach ($field->decorators as $decorator) {
            ($decorator instanceof FieldDecoratorReference ? $decorator->resolve($this->discoveredType->class) : $decorator)->decorate($blueprint);
        }

        $definition = [
            'type' => $registry->resolve($blueprint->typeRef, Position::Output),
            'resolve' => $blueprint->resolver,
        ];

        if ($blueprint->privacy !== null) {
            $definition['privacy'] = $blueprint->privacy;
        }

        $args = $this->args($field, $registry);

        if ($args !== []) {
            $definition['args'] = $args;
        }

        if ($field->description !== null) {
            $definition['description'] = $field->description;
        }

        if ($field->deprecationReason !== null) {
            $definition['deprecationReason'] = $field->deprecationReason;
        }

        return $definition;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function args(DiscoveredTypeField $field, TypeRegistry $registry): array
    {
        $args = [];

        foreach ($field->parameters->args as $arg) {
            $entry = ['type' => $registry->resolve(TypeRef::from($arg->type, nullable: $arg->nullable), Position::Input)];

            if ($arg->hasDefault && $arg->defaultValue !== null) {
                $entry['defaultValue'] = $arg->defaultValue;
            }

            if ($arg->description !== null) {
                $entry['description'] = $arg->description;
            }

            if ($arg->deprecationReason !== null) {
                $entry['deprecationReason'] = $arg->deprecationReason;
            }

            $args[$arg->name] = $entry;
        }

        return $args;
    }

    private function resolver(DiscoveredTypeField $field): Closure
    {
        return match ($field->source) {
            FieldSource::Property => $this->propertyResolver($field),
            FieldSource::Method => $this->methodResolver($field),
            FieldSource::Factory => throw new LogicException(sprintf(
                'Field %s.%s comes from a type factory, which is not supported yet.',
                $this->discoveredType->name,
                $field->name,
            )),
        };
    }

    private function propertyResolver(DiscoveredTypeField $field): Closure
    {
        $property = $field->phpName;
        $path = "{$this->discoveredType->name}.{$field->name}";

        return static fn(mixed $root): mixed => is_object($root)
            ? $root->{$property}
            : throw new RuntimeException("Cannot resolve $path: expected an object, got " . get_debug_type($root) . '.');
    }

    private function methodResolver(DiscoveredTypeField $field): Closure
    {
        $parameters = $field->parameters;

        return function (mixed $root, array $args, mixed $context, ?ResolveInfo $info) use ($field, $parameters): mixed {
            if (! is_object($root)) {
                throw new RuntimeException(
                    "Cannot resolve {$this->discoveredType->name}.{$field->name}: expected an object, got " . get_debug_type($root) . '.',
                );
            }

            $mapped = [];

            foreach ($parameters->args as $arg) {
                $mapped[$arg->paramName] = $args[$arg->name] ?? $arg->defaultValue;
            }

            foreach ($parameters->injections as $paramName => $kind) {
                $mapped[$paramName] = match ($kind) {
                    'root' => $root,
                    'context' => $context,
                    'info' => $info,
                };
            }

            $method = [$root, $field->phpName];

            if (! is_callable($method)) {
                throw new RuntimeException(sprintf(
                    'Cannot resolve %s.%s: %s has no public method %s().',
                    $this->discoveredType->name,
                    $field->name,
                    $root::class,
                    $field->phpName,
                ));
            }

            return $this->app->call($method, $mapped);
        };
    }
}
