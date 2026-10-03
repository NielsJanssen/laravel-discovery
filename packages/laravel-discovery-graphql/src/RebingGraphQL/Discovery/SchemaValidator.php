<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery;

use Illuminate\Contracts\Config\Repository;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredAction;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredField;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Position;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeKind;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRegistry;
use Rebing\GraphQL\Support\Type as RebingType;
use Tempest\Discovery\DiscoveryItems;

/** Cross-item checks on discovered types, operations and the class references between them. */
final class SchemaValidator
{
    public function __construct(
        private readonly TypeRegistry $registry,
        private readonly Repository $config,
        private readonly TypeUsage $usage,
    ) {}

    /** Rejects a type whose GraphQL name or class clashes with an item discovered before it. */
    public function assertNameAvailable(DiscoveryItems $items, DiscoveredType $type): void
    {
        foreach ($items as $item) {
            if ($item instanceof DiscoveredType && $item->name === $type->name && $item->class === $type->class && $item->kind !== $type->kind) {
                throw new LogicException(sprintf(
                    'GraphQL type name [%s] is used by both #[%s] and #[%s] on %s. Rename one with #[%s(name: ...)].',
                    $type->name,
                    $item->kind->attribute(),
                    $type->kind->attribute(),
                    $type->class,
                    $type->kind->attribute(),
                ));
            }

            if ($item instanceof DiscoveredType && $item->name === $type->name && $item->class !== $type->class) {
                throw new LogicException(sprintf(
                    'GraphQL type name [%s] is used by both %s and %s. %s',
                    $type->name,
                    $item->class,
                    $type->class,
                    self::renameHint($type),
                ));
            }
        }
    }

    /**
     * @param  list<DiscoveredType>  $types
     */
    public function validate(DiscoveryItems $items, array $types): void
    {
        $this->assertNoRebingNameClash($items, $types);
        $this->assertUniqueOperations($items);
        $this->assertNoInputFieldArgs($items);
        $references = iterator_to_array($this->usage->references($items, $types), false);

        if ($this->registry->providers() === []) {
            $this->assertRegistered($references);
        } else {
            $this->registry->deferReferences($references);
        }
    }

    /**
     * @param  list<DiscoveredType>  $types
     */
    private function assertNoRebingNameClash(DiscoveryItems $items, array $types): void
    {
        $handWritten = [];

        foreach ($items as $item) {
            if ($item instanceof DiscoveredField && $item->fieldType === 'types' && ($name = $item->getName()) !== null) {
                $handWritten[$name] = $item->class;
            }
        }

        foreach ($types as $type) {
            $taken = $handWritten[$type->name] ?? null;

            if ($taken !== null) {
                throw new LogicException(sprintf(
                    'GraphQL type name [%s] is used by both %s (#[%s]) and the Rebing type %s. %s',
                    $type->name,
                    $type->class,
                    $type->kind->attribute(),
                    $taken,
                    self::renameHint($type),
                ));
            }
        }
    }

    /**
     * Two #[Query] or two #[Mutation] methods would otherwise silently share one field of a schema.
     */
    private function assertUniqueOperations(DiscoveryItems $items): void
    {
        $defaultSchema = $this->config->string('graphql.default_schema', 'default');
        $seen = [];

        foreach ($items as $item) {
            if (! $item instanceof DiscoveredAction || $item->bindName === null) {
                continue;
            }

            $schema = $item->action->schema ?? $defaultSchema;
            $name = $item->action->name ?? $item->method;
            $key = "$schema\0{$item->fieldType}\0$name";
            $previous = $seen[$key] ?? null;

            if ($previous !== null) {
                $attribute = class_basename($item->action::class);

                throw new LogicException(sprintf(
                    'The %s [%s] in schema [%s] is declared by both %s::%s and %s::%s. Rename one with #[%s(name: ...)].',
                    $item->fieldType,
                    $name,
                    $schema,
                    $previous->class,
                    $previous->method,
                    $item->class,
                    $item->method,
                    $attribute,
                ));
            }

            $seen[$key] = $item;
        }
    }

    /**
     * A #[Type] field's args are neither validated, hydrated nor authorized, so none may take a discovered input.
     */
    private function assertNoInputFieldArgs(DiscoveryItems $items): void
    {
        $inputs = [];

        foreach ($items as $item) {
            if ($item instanceof DiscoveredType && $item->kind === TypeKind::Input) {
                $inputs[$item->class] = $item->name;
                $inputs[$item->name] = $item->name;
            }
        }

        foreach ($items as $item) {
            if (! $item instanceof DiscoveredType || $item->kind === TypeKind::Input) {
                continue;
            }

            foreach ($item->fields as $field) {
                foreach ($field->parameters->args as $arg) {
                    $input = $inputs[trim($arg->type->target(), '[]!')] ?? null;

                    if ($input !== null) {
                        throw new LogicException(sprintf(
                            'Argument %s of field %s.%s takes the input type [%s], which fields do not support yet: field args are neither validated, hydrated nor authorized. Take scalar args instead, or move the operation to a #[Query] or #[Mutation].',
                            $arg->name,
                            $item->name,
                            $field->name,
                            $input,
                        ));
                    }
                }
            }
        }
    }

    /**
     * Every class-string an action return or a type field points at must be a registered output type, and every
     * class-string an argument points at a registered input type.
     *
     * @param  iterable<TypeReference>  $references
     */
    public function assertRegistered(iterable $references): void
    {
        foreach ($references as $reference) {
            $class = $reference->ref->class;
            $position = $reference->position;
            $referrer = $reference->referrer;
            $attribute = $reference->attribute;

            if ($class === null || $this->registry->has($class, $position)) {
                continue;
            }

            if ($position === Position::Input) {
                throw new LogicException(sprintf(
                    '%s references %s, which is not a registered GraphQL input type%s. Use a scalar, an enum or an #[Input] class, or name a registered GraphQL input type with #[%s(type: ...)].',
                    $referrer,
                    $class,
                    $this->registry->has($class) ? ' (it is registered as ' . $this->registry->kindOf($class, Position::Output)->value . ' type [' . $this->registry->nameOf($class, Position::Output) . '])' : '',
                    $attribute,
                ));
            }

            if (Input::marks($class)) {
                throw new LogicException(sprintf(
                    '%s references %s, which is an #[Input] and has no output type. Add #[Type] to %s to return it too, or name a registered GraphQL type with type: (or of: for a list) on #[%s].',
                    $referrer,
                    $class,
                    class_basename($class),
                    $attribute,
                ));
            }

            if (is_a($class, RebingType::class, true)) {
                throw new LogicException(sprintf(
                    '%s references the Rebing type class %s. Reference a hand-written Rebing type by its GraphQL name with type: (or of: for a list) on #[%s].',
                    $referrer,
                    $class,
                    $attribute,
                ));
            }

            throw new LogicException(sprintf(
                '%s references %s, which is not a registered GraphQL output type. Add #[Type] to %s, or name a registered GraphQL type with type: (or of: for a list) on #[%s].%s',
                $referrer,
                $class,
                class_basename($class),
                $attribute,
                $this->registry->providers() === [] ? '' : ' A type provider can map the class to a type with TypeDefinition(class:).',
            ));
        }
    }

    private static function renameHint(DiscoveredType $type): string
    {
        if ($type->kind !== TypeKind::Enum) {
            return sprintf('Rename one with #[%s(name: ...)].', $type->kind->attribute());
        }

        return sprintf(
            'Rename one with #[Enum(name: ...)], or register the enum by hand with %s::register() in a service provider that boots before %s.',
            TypeRegistry::class,
            'NielsJanssen\\Laravel\\Discovery\\DiscoveryServiceProvider',
        );
    }
}
