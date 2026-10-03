<?php

declare(strict_types=1);

use GraphQL\Type\Schema;
use GraphQL\Utils\SchemaPrinter;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredAction;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\GraphQLDiscovery;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\Member;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\ScalarMap;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\TypeMapper;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\TypeMapperRegistry;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\Naming;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeKind;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRef;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRegistry;
use PHPUnit\Framework\Assert;
use Rebing\GraphQL\GraphQL as RebingGraphQL;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use Tempest\Reflection\TypeReflector;
use Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * Run a fresh GraphQLDiscovery over fixture classes or anonymous shapes; hand-built types are added as discovered items.
 *
 * @param  class-string|object|DiscoveredType  ...$sources
 */
function discoverGraphQL(string|object ...$sources): GraphQLDiscovery
{
    $discovery = app(GraphQLDiscovery::class);
    $discovery->setItems(new DiscoveryItems());

    $location = new DiscoveryLocation(
        namespace: 'Tests\\Fixtures\\RebingGraphQL',
        path: __DIR__ . '/Fixtures/RebingGraphQL',
    );

    foreach ($sources as $source) {
        if ($source instanceof DiscoveredType) {
            $discovery->getItems()->add($location, $source->bindName === null ? $source->withBindName() : $source);
        } else {
            $discovery->discover($location, new ClassReflector($source));
        }
    }

    return $discovery;
}

/**
 * Assert that discovering the shape throws, naming it `%1$s` and its basename `%2$s` in the format.
 *
 * @param  class-string|object  $shape
 * @param  class-string<Throwable>  $exception
 */
function expectRejected(string|object $shape, string $format, string $exception = LogicException::class): void
{
    $class = is_string($shape) ? $shape : $shape::class;
    $readable = static fn(string $text): string => str_replace("\0", '', $text);
    $expected = sprintf($format, $class, class_basename($class));

    try {
        discoverGraphQL($shape);
    } catch (Throwable $thrown) {
        Assert::assertInstanceOf($exception, $thrown, $readable($thrown::class . ': ' . $thrown->getMessage()));
        Assert::assertTrue(
            str_contains($thrown->getMessage(), $expected),
            "Failed asserting that the message contains\n" . $readable($expected) . "\nGot\n" . $readable($thrown->getMessage()),
        );

        return;
    }

    Assert::fail('Failed asserting that ' . $readable($class) . ' is rejected.');
}

/**
 * @param  class-string|object|DiscoveredType  ...$sources
 * @return array<string, DiscoveredAction> keyed by method name
 */
function discoveredActions(string|object ...$sources): array
{
    $actions = [];

    foreach (discoverGraphQL(...$sources)->getItems() as $item) {
        if ($item instanceof DiscoveredAction) {
            $actions[$item->method] = $item;
        }
    }

    return $actions;
}

/**
 * The discovered types in discovery order.
 *
 * @param  class-string|object|DiscoveredType  ...$sources
 * @return list<DiscoveredType>
 */
function discoveredTypes(string|object ...$sources): array
{
    $items = iterator_to_array(discoverGraphQL(...$sources)->getItems(), false);

    return array_values(array_filter($items, static fn(mixed $item): bool => $item instanceof DiscoveredType));
}

/**
 * The discovered types of one kind in discovery order.
 *
 * @param  class-string|object|DiscoveredType  ...$sources
 * @return list<DiscoveredType>
 */
function discoveredTypesOf(TypeKind $kind, string|object ...$sources): array
{
    return array_values(array_filter(
        discoveredTypes(...$sources),
        static fn(DiscoveredType $type): bool => $type->kind === $kind,
    ));
}

/** A mapper answering with the closure. */
function closureMapper(Closure $map): TypeMapper
{
    return new readonly class ($map) implements TypeMapper {
        public function __construct(private Closure $map) {}

        public function map(TypeReflector $type, Member $member): ?TypeRef
        {
            return ($this->map)($type, $member);
        }
    };
}

/** A mapper claiming every member as a String. */
function greedyMapper(): TypeMapper
{
    return closureMapper(static fn() => TypeRef::scalar('String'));
}

/** Rebuild the scalar map, the mapper registry and the discoverer, tagging any given mapper classes or instances, so a changed config takes effect. */
function refreshMappers(string|TypeMapper ...$mappers): void
{
    $ids = [];

    foreach ($mappers as $mapper) {
        if ($mapper instanceof TypeMapper) {
            $ids[] = $id = 'test.mapper.' . spl_object_id($mapper);
            app()->instance($id, $mapper);
        } else {
            $ids[] = $mapper;
        }
    }

    if ($ids !== []) {
        app()->tag($ids, TypeMapper::TAG);
    }

    app()->forgetInstance(ScalarMap::class);
    app()->forgetInstance(TypeMapperRegistry::class);
    app()->forgetInstance(GraphQLDiscovery::class);
}

/** Run the callback with `config_loaded_from_cache` set on a freshly isolated GraphQL setup. */
function withCachedConfig(Closure $fn): mixed
{
    isolateGraphQL();
    app()->instance('config_loaded_from_cache', true);

    try {
        return $fn();
    } finally {
        app()->forgetInstance('config_loaded_from_cache');
    }
}

/** Isolate, register the scalar types by hand ($types: name => class), and apply the items as they come back from the cache. */
function applyGraphQLItems(DiscoveryItems $items, array $types = []): void
{
    isolateGraphQL();
    config()->set('graphql.types', $types);

    $discovery = app(GraphQLDiscovery::class);
    $discovery->setItems($items);
    $discovery->apply();
}

/** Discover the sources and pass the items through serialize(), as the discovery cache does. */
function cachedGraphQLItems(string|object ...$sources): DiscoveryItems
{
    isolateGraphQL();
    $items = unserialize(serialize(discoverGraphQL(...$sources)->getItems()));

    return $items instanceof DiscoveryItems ? $items : throw new RuntimeException('Items did not survive serialization.');
}

/**
 * Round-trip the sources through serialize() and apply them.
 *
 * @param  list<class-string|object|DiscoveredType>  $sources
 * @param  array<string, class-string>  $types  scalar types registered by hand before applying
 */
function applyCachedGraphQL(array $sources, array $types = []): DiscoveryItems
{
    $items = cachedGraphQLItems(...$sources);
    applyGraphQLItems($items, $types);

    return $items;
}

/**
 * Apply the sources with the configuration cached and assert which discovered types the container binds and that the config stays empty.
 *
 * @param  list<class-string|object|DiscoveredType>  $sources
 * @param  Closure(DiscoveredType): bool  $isBound
 */
function assertBoundWhenConfigCached(array $sources, Closure $isBound): TypeRegistry
{
    return withCachedConfig(function () use ($sources, $isBound): TypeRegistry {
        $discovery = discoverGraphQL(...$sources);
        $discovery->apply();

        foreach ($discovery->getItems() as $item) {
            if ($item instanceof DiscoveredType) {
                Assert::assertSame($isBound($item), app()->bound((string) $item->bindName), "$item->name binding");
            }

            if ($item instanceof DiscoveredAction) {
                Assert::assertTrue(app()->bound((string) $item->bindName), "$item->method binding");
            }
        }

        Assert::assertSame([], config('graphql.types'));

        return app(TypeRegistry::class);
    });
}

/**
 * Run a query against the GraphQL endpoint.
 *
 * @return array<string, mixed>
 */
function queryGraphQL(string $query): array
{
    $json = test()->postJson('/graphql', ['query' => $query])->assertOk()->json();

    return is_array($json) ? $json : [];
}

/** Load the migrations of the Eloquent and loader fixtures. */
function loadGraphQLMigrations(): void
{
    test()->loadMigrationsFrom(__DIR__ . '/Fixtures/RebingGraphQL/migrations');
}

/** Drop the workbench's boot-time GraphQL config, Rebing's cached instance and the type registry. */
function isolateGraphQL(): void
{
    config()->set('graphql.schemas', []);
    config()->set('graphql.types', []);

    app()->forgetInstance(RebingGraphQL::class);
    GraphQL::clearResolvedInstance(RebingGraphQL::class);
    app()->forgetInstance(TypeRegistry::class);
    app()->forgetInstance(Naming::class);
}

/**
 * The schema's top-level definitions, sorted, since the printer follows registration order.
 *
 * @return list<string>
 */
function sdlDefinitions(string $sdl): array
{
    $definitions = preg_split('/\n\n(?=\S)/', trim($sdl)) ?: [];
    sort($definitions);

    return $definitions;
}

/**
 * Print the default schema built from only the given sources; it needs at least one query.
 *
 * @param  class-string|object|DiscoveredType  ...$sources
 */
function schemaSdl(string|object ...$sources): string
{
    return schemaSdlWith([], ...$sources);
}

/**
 * Like schemaSdl(), with scalar types registered by hand first (name => class).
 *
 * @param  array<string, class-string>  $types
 * @param  class-string|object|DiscoveredType  ...$sources
 */
function schemaSdlWith(array $types, string|object ...$sources): string
{
    isolateGraphQL();
    config()->set('graphql.types', $types);
    discoverGraphQL(...$sources)->apply();

    return SchemaPrinter::doPrint(GraphQL::schema());
}

/**
 * Build and validate every configured schema, so lazy type resolution errors surface.
 *
 * @return array<string, Schema>
 */
function buildAllSchemas(): array
{
    $schemas = [];

    foreach (array_keys(config()->array('graphql.schemas')) as $name) {
        $schemas[$name] = GraphQL::schema((string) $name);
        $schemas[$name]->assertValid();
    }

    return $schemas;
}
