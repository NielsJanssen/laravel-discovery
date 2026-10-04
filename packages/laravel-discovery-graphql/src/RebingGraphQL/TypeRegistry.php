<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use GraphQL\Type\Definition\NullableType;
use GraphQL\Type\Definition\Type as GraphQLType;
use Illuminate\Container\Attributes\Singleton;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\SchemaScopes;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\TypeReference;
use Rebing\GraphQL\Support\Facades\GraphQL;
use RuntimeException;

#[Singleton]
final class TypeRegistry
{
    /** @var array<class-string, array<string, string>> GraphQL name keyed by class, then by kind */
    private array $names = [];

    /** @var array<string, class-string> */
    private array $classes = [];

    /** @var array<string, DiscoveredType> */
    private array $types = [];

    /** @var array<string, array<class-string, class-string>> the class that replaces a class, keyed by kind */
    private array $replacements = [];

    /** @var array<class-string, class-string> */
    private array $providers = [];

    /** @var list<TypeReference> the class references to check once the providers have registered their types */
    private array $deferred = [];

    /** @var list<DiscoveredExtension> the #[TypeExtension] contributors to attach once the providers have registered their types */
    private array $deferredExtensions = [];

    /** @var array<string, array<string, array{0: TypeReference|null, 1: string}>> per GraphQL name or class: the referrer and origin by which each schema reaches it */
    private array $reach = [];

    /** @var array<string, list<string>> the schemas a type is placed in with schema:, keyed by GraphQL name */
    private array $placements = [];

    /**
     * @param class-string $class
     */
    public function register(string $class, string $name, TypeKind $kind): void
    {
        $taken = $this->classes[$name] ?? null;

        if ($taken !== null && $taken !== $class) {
            throw new LogicException("Cannot register $class as GraphQL type [$name]: that name already belongs to $taken.");
        }

        $existing = $this->names[$class][$kind->value] ?? null;

        if ($existing !== null && $existing !== $name) {
            throw new LogicException("Cannot register $class as {$kind->value} type [$name]: it is already registered as [$existing].");
        }

        $this->names[$class][$kind->value] = $name;
        $this->classes[$name] = $class;
    }

    /**
     * Makes a replaced class resolve to the GraphQL type of its replacement.
     *
     * @param class-string $replaced
     * @param class-string $replacement
     */
    public function replace(string $replaced, string $replacement, TypeKind $kind): void
    {
        $this->replacements[$kind->value][$replaced] = $replacement;
        $name = $this->names[$replacement][$kind->value] ?? null;

        if ($name !== null) {
            $this->names[$replaced][$kind->value] = $name;
        }
    }

    /**
     * The class to build for a class: its most specific replacement, or itself.
     *
     * @param class-string $class
     * @return class-string
     */
    public function effective(string $class, TypeKind $kind): string
    {
        return $this->replacements[$kind->value][$class] ?? $class;
    }

    /** Keeps a discovered type's description, so a request can read the fields behind its GraphQL name. */
    public function describe(DiscoveredType $type): void
    {
        $this->types[$type->name] = $type;
    }

    /**
     * @param class-string $class
     */
    public function addProvider(string $class): void
    {
        $this->providers[$class] = $class;
    }

    /**
     * @return list<class-string>
     */
    public function providers(): array
    {
        return array_values($this->providers);
    }

    /**
     * @param list<TypeReference> $references
     */
    public function deferReferences(array $references): void
    {
        $this->deferred = $references;
    }

    /**
     * @return list<TypeReference>
     */
    public function deferredReferences(): array
    {
        return $this->deferred;
    }

    /**
     * @param list<DiscoveredExtension> $extensions
     */
    public function deferExtensions(array $extensions): void
    {
        $this->deferredExtensions = $extensions;
    }

    /**
     * @return list<DiscoveredExtension>
     */
    public function deferredExtensions(): array
    {
        return $this->deferredExtensions;
    }

    /**
     * @param  array<string, array<string, array{0: TypeReference|null, 1: string}>>  $reach
     * @param  array<string, list<string>>  $placements
     */
    public function scope(array $reach, array $placements): void
    {
        $this->reach = $reach;
        $this->placements = $placements;
    }

    /**
     * @param  list<string>  $schemas
     */
    public function place(string $name, array $schemas): void
    {
        $this->placements[$name] = $schemas;
    }

    /** Files what reaches a provided type's class under its GraphQL name. */
    public function adopt(string $name, ?string $class): void
    {
        if ($class !== null && isset($this->reach[$class])) {
            $this->reach[$name] = [...$this->reach[$class], ...$this->reach[$name] ?? []];
        }
    }

    /**
     * @return array<string, array{0: TypeReference|null, 1: string}>  the referrer and origin by which each schema reaches the type
     */
    public function reachOf(string $name): array
    {
        return $this->reach[$name] ?? [];
    }

    /** Whether an action of the schema reaches the type, directly or through a type every schema lists. */
    public function reaches(string $schema, string $name): bool
    {
        return isset($this->reach[$name][$schema]) || isset($this->reach[$name][SchemaScopes::EVERY]);
    }

    /** Whether the schema lists the type: a placed type in its schemas, a reached type where it is reached, any other type everywhere. */
    public function allows(string $schema, string $name): bool
    {
        $placed = $this->placements[$name] ?? null;

        if ($placed !== null) {
            return in_array($schema, $placed, true);
        }

        return ! isset($this->reach[$name]) || $this->reaches($schema, $name);
    }

    public function typeNamed(string $name): ?DiscoveredType
    {
        return $this->types[$name] ?? null;
    }

    /**
     * @param class-string $class
     */
    public function has(string $class, ?Position $position = null): bool
    {
        return $this->registration($class, $position) !== null;
    }

    /**
     * @param class-string $class
     */
    public function nameOf(string $class, Position $position): string
    {
        return $this->registration($class, $position)[0] ?? throw new RuntimeException($this->unregisteredMessage($class, $position));
    }

    /**
     * @param class-string $class
     */
    public function kindOf(string $class, Position $position): TypeKind
    {
        return $this->registration($class, $position)[1] ?? throw new RuntimeException($this->unregisteredMessage($class, $position));
    }

    /**
     * The GraphQL name a reference points at; scalars keep their scalar name.
     */
    public function name(TypeRef $ref, Position $position): string
    {
        return match (true) {
            $ref->class !== null => $this->nameOf($ref->class, $position),
            $ref->scalar !== null => $ref->scalar,
            default => (string) $ref->name,
        };
    }

    public function resolve(TypeRef $ref, Position $position): GraphQLType
    {
        $type = match (true) {
            $ref->scalar !== null => $this->scalar($ref->scalar, $position),
            $ref->class !== null => GraphQL::type($this->nameOf($ref->class, $position)),
            default => GraphQL::type((string) $ref->name),
        };

        if ($ref->list) {
            $type = GraphQLType::listOf($ref->nullableItems ? $type : GraphQLType::nonNull($this->nullable($type)));
        }

        return $ref->nullable ? $type : GraphQLType::nonNull($this->nullable($type));
    }

    /**
     * @param class-string $class
     * @return array{0: string, 1: TypeKind}|null
     */
    private function registration(string $class, ?Position $position): ?array
    {
        foreach ($this->names[$class] ?? [] as $kind => $name) {
            $kind = TypeKind::from($kind);

            if ($position === null || $kind->usableAt($position)) {
                return [$name, $kind];
            }
        }

        return null;
    }

    /**
     * @param class-string $class
     */
    private function unregisteredMessage(string $class, Position $position): string
    {
        $registered = $this->names[$class] ?? [];

        if ($registered !== []) {
            return sprintf(
                '%s is registered as %s, which cannot be used in %s position.',
                $class,
                implode(', ', array_map(
                    static fn(string $kind, string $name): string => "$kind type [$name]",
                    array_keys($registered),
                    $registered,
                )),
                $position->value,
            );
        }

        return sprintf(
            '%s is not a registered GraphQL %s type. Register it with %s::register(), or reference the type by its GraphQL name.',
            $class,
            $position->value,
            self::class,
        );
    }

    private function scalar(string $scalar, Position $position): GraphQLType
    {
        return match ($scalar) {
            'string', 'String' => GraphQLType::string(),
            'int', 'Int' => GraphQLType::int(),
            'float', 'Float' => GraphQLType::float(),
            'bool', 'Boolean' => GraphQLType::boolean(),
            'ID' => GraphQLType::id(),
            'void' => $position === Position::Output
                ? new NullType()
                : throw new LogicException('The void type has no input form; it can only be returned.'),
            default => throw new LogicException("Unknown scalar [$scalar]."),
        };
    }

    /**
     * @return NullableType&GraphQLType
     */
    private function nullable(GraphQLType $type): GraphQLType
    {
        if (! $type instanceof NullableType) {
            throw new LogicException("GraphQL type [$type] is already non-null and cannot be wrapped again.");
        }

        return $type;
    }
}
