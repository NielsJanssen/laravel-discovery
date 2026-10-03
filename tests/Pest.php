<?php

declare(strict_types=1);

use GraphQL\Type\Schema;
use GraphQL\Utils\SchemaPrinter;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredAction;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\GraphQLDiscovery;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRegistry;
use Rebing\GraphQL\GraphQL as RebingGraphQL;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * Run a fresh GraphQLDiscovery over fixture classes; hand-built types are added as discovered items.
 *
 * @param  class-string|DiscoveredType  ...$sources
 */
function discoverGraphQL(string|DiscoveredType ...$sources): GraphQLDiscovery
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
 * @return array<string, DiscoveredAction> keyed by method name
 */
function discoveredActions(string ...$classes): array
{
    $actions = [];

    foreach (discoverGraphQL(...$classes)->getItems() as $item) {
        if ($item instanceof DiscoveredAction) {
            $actions[$item->method] = $item;
        }
    }

    return $actions;
}

/** Drop the workbench's boot-time GraphQL config, Rebing's cached instance and the type registry. */
function isolateGraphQL(): void
{
    config()->set('graphql.schemas', []);
    config()->set('graphql.types', []);

    app()->forgetInstance(RebingGraphQL::class);
    GraphQL::clearResolvedInstance(RebingGraphQL::class);
    app()->forgetInstance(TypeRegistry::class);
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
 * @param  class-string|DiscoveredType  ...$sources
 */
function schemaSdl(string|DiscoveredType ...$sources): string
{
    isolateGraphQL();
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
