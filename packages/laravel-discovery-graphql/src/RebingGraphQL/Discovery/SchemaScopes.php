<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery;

use Illuminate\Contracts\Config\Repository;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredAction;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredExtension;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeDefinition;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRegistry;
use Tempest\Discovery\DiscoveryItems;

/** Which schemas reach each type, through the actions of each schema and the types placed in it with schema:. */
final readonly class SchemaScopes
{
    /** The schema key of a type every schema lists: reached from a type that no action reaches and no schema: places. */
    public const string EVERY = '*';

    public function __construct(
        private TypeRegistry $registry,
        private Repository $config,
        private TypeUsage $usage,
    ) {}

    public static function enabled(Repository $config): bool
    {
        return $config->boolean('discovery.graphql.scoped_schemas', true);
    }

    /**
     * Computes the reach of every type, checks the placements and hands both to the registry.
     *
     * @param  list<DiscoveredType>  $types  the registered types, with their contributed fields
     * @param  list<DiscoveredExtension>  $deferred  the contributors of provided types
     */
    public function apply(DiscoveryItems $items, array $types, array $deferred): void
    {
        if (! self::enabled($this->config)) {
            $this->assertUnplaced($types);

            return;
        }

        $this->assertSchemasExist($types);
        $reach = $this->reach($items, $types, $deferred);
        $placements = [];

        foreach ($types as $type) {
            if ($type->schemas !== []) {
                $this->assertPlacement($type, $reach[$type->name] ?? []);
                $placements[$type->name] = $type->schemas;
            }
        }

        $this->registry->scope($reach, $placements);
    }

    /** Scopes a provided type by what reaches its name or class, and checks its placement. */
    public function provide(string $provider, TypeDefinition $definition): void
    {
        $schemas = (array) $definition->schema;

        if (! self::enabled($this->config)) {
            if ($schemas !== []) {
                throw new LogicException(sprintf(
                    'The type provider %s yields [%s] with schema:, which needs schema-scoped types. Set discovery.graphql.scoped_schemas to true, or remove schema:.',
                    $provider,
                    $definition->name,
                ));
            }

            return;
        }

        $this->registry->adopt($definition->name, $definition->class);

        if ($schemas === []) {
            return;
        }

        foreach ($schemas as $schema) {
            if (! $this->exists($schema)) {
                throw new LogicException(sprintf(
                    'The type provider %s yields [%s] with schema: [%s], which is not in graphql.schemas and no action uses it. Fix the name, or add the schema.',
                    $provider,
                    $definition->name,
                    $schema,
                ));
            }
        }

        foreach ($this->registry->reachOf($definition->name) as $schema => [$referrer, $origin]) {
            if (in_array($schema, $schemas, true)) {
                continue;
            }

            throw new LogicException($schema === self::EVERY ? sprintf(
                'The type provider %s yields [%s] with schema: [%s], but %s references it from %s, which is in every schema: no action reaches it and it sets no schema:. Place %s with schema: too, or drop schema: from the definition.',
                $provider,
                $definition->name,
                implode(', ', $schemas),
                $referrer->referrer ?? $origin,
                $origin,
                $origin,
            ) : sprintf(
                'The type provider %s yields [%s] with schema: [%s], but schema [%s] reaches it through %s. Add \'%s\' to schema:, or drop schema: so the type follows the actions that use it.',
                $provider,
                $definition->name,
                implode(', ', $schemas),
                $schema,
                $referrer->referrer ?? $origin,
                $schema,
            ));
        }

        $this->registry->place($definition->name, $schemas);
    }

    /**
     * Rejects a schema: that names no schema, before the placements are checked against the reach.
     *
     * @param  list<DiscoveredType>  $types
     */
    private function assertSchemasExist(array $types): void
    {
        foreach ($types as $type) {
            foreach ($type->schemas as $schema) {
                if (! $this->exists($schema)) {
                    throw new LogicException(sprintf(
                        '#[%s(schema: ...)] on %s names schema [%s], which is not in graphql.schemas and no action uses it. Fix the name, or add the schema.',
                        $type->kind->attribute(),
                        $type->class,
                        $schema,
                    ));
                }
            }
        }
    }

    private function exists(string $schema): bool
    {
        return array_key_exists($schema, $this->config->array('graphql.schemas', []));
    }

    /**
     * @param  list<DiscoveredType>  $types
     */
    private function assertUnplaced(array $types): void
    {
        foreach ($types as $type) {
            if ($type->schemas !== []) {
                throw new LogicException(sprintf(
                    '#[%s(schema: ...)] on %s needs schema-scoped types. Set discovery.graphql.scoped_schemas to true, or remove schema:.',
                    $type->kind->attribute(),
                    $type->class,
                ));
            }
        }
    }

    /**
     * @param  array<string, array{0: TypeReference|null, 1: string}>  $reach
     */
    private function assertPlacement(DiscoveredType $type, array $reach): void
    {
        foreach ($reach as $schema => [$referrer, $origin]) {
            if (in_array($schema, $type->schemas, true) || $referrer === null) {
                continue;
            }

            $target = $referrer->ref->class ?? (string) $referrer->ref->name;
            $placed = implode(', ', $type->schemas);
            $attribute = $type->kind->attribute();

            throw new LogicException($schema === self::EVERY ? sprintf(
                '%s references %s, which #[%s(schema: ...)] on %s places in schema [%s], but %s is in every schema: no action reaches it and it sets no schema:. Place %s with schema: too, or remove schema: from %s.',
                $referrer->referrer,
                $target,
                $attribute,
                $type->class,
                $placed,
                $origin,
                $origin,
                $type->class,
            ) : sprintf(
                '%s references %s, which #[%s(schema: ...)] on %s places in schema [%s], but schema [%s] reaches it through %s. Add \'%s\' to schema:, or remove schema: so the type follows the actions that use it.',
                $referrer->referrer,
                $target,
                $attribute,
                $type->class,
                $placed,
                $schema,
                $origin,
                $schema,
            ));
        }
    }

    /**
     * Walks from each schema's actions and placed types, then from the types every schema lists.
     *
     * @param  list<DiscoveredType>  $types
     * @param  list<DiscoveredExtension>  $deferred
     * @return array<string, array<string, array{0: TypeReference|null, 1: string}>>
     */
    private function reach(DiscoveryItems $items, array $types, array $deferred): array
    {
        $edges = [];

        foreach ($types as $type) {
            $edges[$type->name] = iterator_to_array($this->usage->ofType($type, contributed: true), false);
        }

        foreach ($deferred as $extension) {
            $edges[$extension->target] = array_merge($edges[$extension->target] ?? [], iterator_to_array($this->usage->ofExtension($extension), false));
        }

        $default = $this->config->string('graphql.default_schema', 'default');
        $reach = [];

        foreach ($items as $item) {
            if ($item instanceof DiscoveredAction && $item->bindName !== null) {
                foreach ($this->usage->ofAction($item) as $reference) {
                    $this->visit($reach, $edges, $item->action->schema ?? $default, "{$item->class}::{$item->method}", $reference);
                }
            }
        }

        foreach ($types as $type) {
            foreach ($type->schemas as $schema) {
                $reach[$type->name][$schema] ??= [null, $type->class];

                foreach ($edges[$type->name] as $reference) {
                    $this->visit($reach, $edges, $schema, $type->class, $reference);
                }
            }
        }

        $global = array_filter($types, static fn(DiscoveredType $type): bool => $type->schemas === [] && ! isset($reach[$type->name]));

        foreach ($global as $type) {
            foreach ($edges[$type->name] as $reference) {
                $this->visit($reach, $edges, self::EVERY, $type->name, $reference);
            }
        }

        return $reach;
    }

    /**
     * Marks the reference's target and everything it reaches as reached from the schema.
     *
     * @param  array<string, array<string, array{0: TypeReference|null, 1: string}>>  $reach
     * @param  array<string, list<TypeReference>>  $edges
     */
    private function visit(array &$reach, array $edges, string $schema, string $origin, TypeReference $reference): void
    {
        $pending = [$reference];

        while ($pending !== []) {
            $reference = array_pop($pending);
            $key = $this->keyOf($reference);

            if (isset($reach[$key][$schema])) {
                continue;
            }

            $reach[$key][$schema] = [$reference, $origin];

            foreach ($edges[$key] ?? [] as $next) {
                $pending[] = $next;
            }
        }
    }

    /** The GraphQL name a reference points at, or the class when no registered type has it yet. */
    private function keyOf(TypeReference $reference): string
    {
        $class = $reference->ref->class;

        if ($class === null) {
            return trim((string) $reference->ref->name, '[]!');
        }

        return $this->registry->has($class, $reference->position) ? $this->registry->nameOf($class, $reference->position) : $class;
    }
}
