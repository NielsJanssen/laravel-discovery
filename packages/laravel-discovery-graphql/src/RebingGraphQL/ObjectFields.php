<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Foundation\Application;
use LogicException;
use RuntimeException;

/** Builds the Rebing field definitions of an object type: its collected fields, then its factory and contributor fields. */
final readonly class ObjectFields
{
    public function __construct(
        private Application $app,
        private TypeRegistry $registry,
        private FactoryFields $factories,
    ) {}

    /**
     * @param  class-string|null  $class  the class of the type, which a provided type may lack
     * @param  list<string>  $taken  the field names the type already has from elsewhere
     * @return array<string, array<string, mixed>>
     */
    public function build(DiscoveredType $type, ?string $class, array $taken = []): array
    {
        $fields = [];

        foreach ($type->fields as $field) {
            $fields[$field->name] = $this->definition($type, $field);
        }

        if ($type->factory !== null) {
            $fields += $this->factories->definitions($type, Position::Output);
        }

        return $fields + $this->factories->contributed($type, $class, [...$taken, ...array_keys($fields)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(DiscoveredType $type, DiscoveredTypeField $field): array
    {
        $blueprint = new FieldBlueprint($this->app, $type, $field, $field->type, $this->resolver($type, $field));

        foreach ($field->decorators as $decorator) {
            ($decorator instanceof FieldDecoratorReference ? $decorator->resolve($field->host ?? $type->class) : $decorator)->decorate($blueprint);
        }

        $definition = [
            'type' => $this->registry->resolve($blueprint->typeRef, Position::Output),
            'resolve' => $blueprint->resolver,
        ];

        if ($blueprint->privacy !== null) {
            $definition['privacy'] = $blueprint->privacy;
        }

        $args = $this->args($field);

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
    private function args(DiscoveredTypeField $field): array
    {
        $args = [];

        foreach ($field->parameters->args as $arg) {
            $entry = ['type' => $this->registry->resolve($arg->type, Position::Input)];

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

    private function resolver(DiscoveredType $type, DiscoveredTypeField $field): Closure
    {
        return match ($field->source) {
            FieldSource::Property => $this->propertyResolver($type, $field),
            FieldSource::Method => $this->methodResolver($type, $field),
            FieldSource::Factory => throw new LogicException(sprintf(
                'Field %s.%s comes from a type factory, which is not supported yet.',
                $type->name,
                $field->name,
            )),
        };
    }

    private function propertyResolver(DiscoveredType $type, DiscoveredTypeField $field): Closure
    {
        $property = $field->phpName;
        $path = "{$type->name}.{$field->name}";

        return static fn(mixed $root): mixed => is_object($root)
            ? (property_exists($root, $property) || method_exists($root, '__get') ? $root->{$property} : null)
            : throw new RuntimeException("Cannot resolve $path: expected an object, got " . get_debug_type($root) . '.');
    }

    private function methodResolver(DiscoveredType $type, DiscoveredTypeField $field): Closure
    {
        $parameters = $field->parameters;
        $app = $this->app;
        $path = "{$type->name}.{$field->name}";

        return static function (mixed $root, array $args, mixed $context, ?ResolveInfo $info) use ($app, $field, $parameters, $path): mixed {
            if (! is_object($root) && $field->host === null) {
                throw new RuntimeException("Cannot resolve $path: expected an object, got " . get_debug_type($root) . '.');
            }

            $mapped = [];

            foreach ($parameters->args as $arg) {
                $mapped[$arg->paramName] = $args[$arg->name] ?? $arg->defaultValue;
            }

            $mapped = [...$mapped, ...Injections::values($parameters->injections, $root, $context, $info)];

            $method = [$field->host === null ? $root : $app->make($field->host), $field->phpName];

            if (! is_callable($method)) {
                return null;
            }

            return $app->call($method, $mapped);
        };
    }
}
