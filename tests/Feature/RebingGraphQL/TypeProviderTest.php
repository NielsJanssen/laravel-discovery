<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use GraphQL\Type\Definition\ResolveInfo;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredTypeProvider;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Position;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeContext;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeDefinition;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeProvider;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRegistry;
use Rebing\GraphQL\GraphQL as RebingGraphQL;
use Rebing\GraphQL\Support\Facades\GraphQL;
use stdClass;
use Tests\Fixtures\RebingGraphQL\Providers\AcmeConfigurableProvider;
use Tests\Fixtures\RebingGraphQL\Providers\AcmeFilterProvider;
use Tests\Fixtures\RebingGraphQL\Providers\AcmeMetaProvider;
use Tests\Fixtures\RebingGraphQL\Providers\AcmeProvidedQueries;
use Tests\Fixtures\RebingGraphQL\Providers\AcmeShipment;
use Tests\Fixtures\RebingGraphQL\Providers\AcmeShipmentProvider;
use Tests\Fixtures\RebingGraphQL\Providers\AcmeShipmentQuery;
use Tests\Fixtures\RebingGraphQL\Providers\AcmeWarehouseQuery;
use Tests\Fixtures\RebingGraphQL\Replacement\AcmeUser;

const PROVIDED_SOURCES = [AcmeWarehouseQuery::class, AcmeProvidedQueries::class, AcmeMetaProvider::class, AcmeFilterProvider::class];

/**
 * @param  list<mixed>  $definitions
 * @param  class-string  ...$sources
 */
function provideTypes(array $definitions, string ...$sources): string
{
    app()->instance(AcmeConfigurableProvider::class, new AcmeConfigurableProvider($definitions));

    return schemaSdl(AcmeWarehouseQuery::class, AcmeConfigurableProvider::class, ...$sources);
}

/**
 * @param  Closure(TypeContext): iterable<Field>|list<Field>  $fields
 */
function acmeWarehouse(): TypeDefinition
{
    return acmeDefinition('AcmeWarehouse', Position::Output, [new Field(name: 'name', type: 'string'), new Field(name: 'capacity', type: 'int')]);
}

function acmeDefinition(string $name, Position $kind, array|\Closure $fields, ?string $class = null): TypeDefinition
{
    return new TypeDefinition($name, $kind, is_array($fields) ? static fn() => $fields : $fields, $class);
}

describe('discovery', function () {
    it('keeps only the class name of a provider', function () {
        $items = cachedGraphQLItems(AcmeMetaProvider::class);
        $providers = array_values(array_filter([...$items], static fn(object $item): bool => $item instanceof DiscoveredTypeProvider));

        expect($providers)->toEqual([new DiscoveredTypeProvider(AcmeMetaProvider::class)]);
    });

    it('finds a provider by its interface and nothing else', function () {
        $items = discoverGraphQL(AcmeMetaProvider::class, stdClass::class, AcmeWarehouseQuery::class)->getItems();
        $providers = array_filter([...$items], static fn(object $item): bool => $item instanceof DiscoveredTypeProvider);

        expect($providers)->toHaveCount(1);
    });

    it('does not run the provider during discovery', function () {
        app()->instance(AcmeConfigurableProvider::class, new class implements TypeProvider {
            public function types(): iterable
            {
                throw new \RuntimeException('ran');
            }
        });

        expect(discoverGraphQL(AcmeConfigurableProvider::class)->getItems())->toHaveCount(1);
    });
});

describe('output types', function () {
    it('yields two types, described by the service the provider is injected with', function () {
        $definitions = sdlDefinitions(schemaSdl(...PROVIDED_SOURCES));

        expect($definitions)->toContain(<<<'GRAPHQL'
            "A provided AcmeWarehouse!"
            type AcmeWarehouse {
              "The AcmeWarehouse name"
              name: String!

              "The AcmeWarehouse capacity"
              capacity: Int!
            }
            GRAPHQL)
            ->and($definitions)->toContain(<<<'GRAPHQL'
            "A provided AcmeDepot!"
            type AcmeDepot {
              "The AcmeDepot code"
              code: String!
            }
            GRAPHQL);
    });

    it('resolves a provided type from an array root', function () {
        schemaSdl(...PROVIDED_SOURCES);

        expect(GraphQL::query('{ warehouse { name capacity } depots { code } }'))->toBe(['data' => [
            'warehouse' => ['name' => 'North', 'capacity' => 40],
            'depots' => [['code' => 'D1'], ['code' => 'D2']],
        ]]);
    });

    it('gives a provided field a resolver and args', function () {
        $sdl = provideTypes([acmeDefinition('AcmeWarehouse', Position::Output, [
            new Field(name: 'shout', type: 'string', args: ['text' => new Field(type: 'string')], resolve: fn($root, array $args, $context, ResolveInfo $info): string => strtoupper($args['text']) . '@' . $info->fieldName),
        ])]);

        expect($sdl)->toContain('shout(text: String!): String!')
            ->and(GraphQL::query('{ warehouse { shout(text: "hi") } }'))->toBe(['data' => ['warehouse' => ['shout' => 'HI@shout']]]);
    });

    it('tells the fields closure what it builds', function () {
        $seen = null;

        provideTypes([new TypeDefinition('AcmeWarehouse', Position::Output, function (TypeContext $context) use (&$seen): array {
            $seen = $context;

            return [new Field(name: 'name', type: 'string')];
        })]);
        GraphQL::query('{ warehouse { name } }');

        expect($seen)->toBeInstanceOf(TypeContext::class)
            ->and($seen->name)->toBe('AcmeWarehouse')
            ->and($seen->class)->toBeNull()
            ->and($seen->kind)->toBe(Position::Output)
            ->and($seen->declaredFields)->toBe([]);
    });

    it('serves a provided type from the discovery cache', function () {
        applyCachedGraphQL(PROVIDED_SOURCES);

        expect(GraphQL::query('{ warehouse { name } }'))->toBe(['data' => ['warehouse' => ['name' => 'North']]]);
    });

    it('keeps the config out of it', function () {
        schemaSdl(...PROVIDED_SOURCES);

        expect(config('graphql.types'))->not->toHaveKey('AcmeWarehouse');
    });
});

describe('input types', function () {
    it('prints a provided input type', function () {
        expect(sdlDefinitions(schemaSdl(...PROVIDED_SOURCES)))->toContain(<<<'GRAPHQL'
            "What to look for"
            input AcmeFilter {
              term: String!

              "At most this many"
              limit: Int
              tags: [String!]
            }
            GRAPHQL);
    });

    it('hands the action the value as a plain array', function () {
        schemaSdl(...PROVIDED_SOURCES);

        expect(GraphQL::query('{ describeFilter(filter: {term: "bolt", tags: ["a"]}) }'))
            ->toBe(['data' => ['describeFilter' => '{"term":"bolt","tags":["a"]}']]);
    });

    it('rejects a missing required field of the provided input', function () {
        schemaSdl(...PROVIDED_SOURCES);

        $result = GraphQL::query('{ describeFilter(filter: {limit: 1}) }');

        expect($result)->toHaveKey('errors')
            ->and($result['errors'][0]['message'])->toContain('term');
    });

    it('rejects what an input field cannot do', function (Field $field, string $message) {
        expect(fn() => provideTypes([acmeWarehouse(), acmeDefinition('AcmeFilter', Position::Input, [$field])]))
            ->toThrow(LogicException::class, $message);
    })->with([
        'resolve' => [new Field(name: 'term', type: 'string', resolve: fn() => 'x'), 'sets resolve: or args:, which an input field cannot use'],
        'args' => [new Field(name: 'term', type: 'string', args: ['a' => new Field(type: 'string')]), 'sets resolve: or args:, which an input field cannot use'],
        'rules' => [new Field(name: 'term', type: 'string', rules: ['min:2']), 'sets rules:, which are not applied to a field yielded for an input type'],
    ]);

    it('rejects a class on an input definition', function () {
        expect(fn() => provideTypes([acmeWarehouse(), acmeDefinition('AcmeFilter', Position::Input, [], stdClass::class)]))
            ->toThrow(LogicException::class, sprintf("yields the input type [AcmeFilter] with class: stdClass, which is not supported yet. Remove class:, and take the value as an array with #[Arg(type: 'AcmeFilter')].", ));
    });
});

describe('rejections', function () {
    it('rejects what a provider yields that is no type definition', function () {
        expect(fn() => provideTypes(['AcmeWarehouse']))
            ->toThrow(LogicException::class, sprintf('The type provider %s yielded string, which is no %s.', AcmeConfigurableProvider::class, TypeDefinition::class));
    });

    it('rejects a provider the container builds as something else', function () {
        app()->bind(AcmeConfigurableProvider::class, fn() => new stdClass());
        schemaSdl(AcmeWarehouseQuery::class, AcmeConfigurableProvider::class);
    })->throws(LogicException::class, 'resolves to stdClass, which is no ' . TypeProvider::class);

    it('rejects two types with one name', function () {
        expect(fn() => provideTypes([acmeDefinition('AcmeWarehouse', Position::Output, []), acmeDefinition('AcmeWarehouse', Position::Output, [])]))
            ->toThrow(LogicException::class, 'yields a type named [AcmeWarehouse], which is already registered.');
    });

    it('rejects a name the configuration already registers', function () {
        isolateGraphQL();
        config()->set('graphql.types', ['AcmeWarehouse' => stdClass::class]);
        app()->instance(AcmeConfigurableProvider::class, new AcmeConfigurableProvider([acmeDefinition('AcmeWarehouse', Position::Output, [])]));
        discoverGraphQL(AcmeWarehouseQuery::class, AcmeConfigurableProvider::class)->apply();

        expect(fn() => GraphQL::schema())->toThrow(LogicException::class, 'yields a type named [AcmeWarehouse], which is already registered.');
    });

    it('rejects a provided field without a name or a type', function () {
        expect(fn() => provideTypes([acmeDefinition('AcmeWarehouse', Position::Output, [new Field(type: 'string')])]))
            ->toThrow(LogicException::class, sprintf('The type provider %s yielded a field without a name for type [AcmeWarehouse].', AcmeConfigurableProvider::class));

        expect(fn() => provideTypes([acmeDefinition('AcmeWarehouse', Position::Output, [new Field(name: 'name')])]))
            ->toThrow(LogicException::class, 'Field "name" from the type provider');
    });
});

describe('class mapping', function () {
    it('infers the provided type from a return typed as its class', function () {
        $sdl = schemaSdl(AcmeShipmentQuery::class, AcmeShipmentProvider::class);

        expect($sdl)->toContain('shipment: AcmeConsignment!')
            ->and($sdl)->toContain('maybeShipment: AcmeConsignment')
            ->and($sdl)->not->toContain('maybeShipment: AcmeConsignment!')
            ->and($sdl)->toContain('shipments: [AcmeConsignment!]!');
    });

    it('resolves the provided type from an instance of its class', function () {
        schemaSdl(AcmeShipmentQuery::class, AcmeShipmentProvider::class);

        expect(GraphQL::query('{ shipment { reference weight } maybeShipment { reference } shipments { reference } }'))->toBe(['data' => [
            'shipment' => ['reference' => 'S-1', 'weight' => 12],
            'maybeShipment' => null,
            'shipments' => [['reference' => 'S-1'], ['reference' => 'S-2']],
        ]]);
    });

    it('maps the class in the registry', function () {
        schemaSdl(AcmeShipmentQuery::class, AcmeShipmentProvider::class);
        GraphQL::schema();

        expect(app(TypeRegistry::class)->nameOf(AcmeShipment::class, Position::Output))->toBe('AcmeConsignment');
    });

    it('serves a class-mapped type from the discovery cache', function () {
        applyCachedGraphQL([AcmeShipmentQuery::class, AcmeShipmentProvider::class]);

        expect(GraphQL::query('{ shipment { reference } }'))->toBe(['data' => ['shipment' => ['reference' => 'S-1']]]);
    });

    it('rejects a class a #[Type] already maps', function () {
        expect(fn() => provideTypes([acmeWarehouse(), acmeDefinition('AcmeOtherUser', Position::Output, [new Field(name: 'name', type: 'string')], AcmeUser::class)], AcmeUser::class))
            ->toThrow(LogicException::class, sprintf('Cannot register %s as object type [AcmeOtherUser]: it is already registered as [AcmeUser].', AcmeUser::class));
    });
});

describe('the deferred class check', function () {
    it('rejects an unknown class at apply() when no provider exists', function () {
        isolateGraphQL();

        expect(fn() => discoverGraphQL(AcmeShipmentQuery::class)->apply())
            ->toThrow(LogicException::class, sprintf('Method %s::shipment references %s, which is not a registered GraphQL output type.', AcmeShipmentQuery::class, AcmeShipment::class));
    });

    it('defers the check to schema build when a provider exists', function () {
        isolateGraphQL();
        discoverGraphQL(AcmeShipmentQuery::class, AcmeMetaProvider::class)->apply();

        expect(fn() => GraphQL::schema())
            ->toThrow(LogicException::class, sprintf('Method %s::shipment references %s, which is not a registered GraphQL output type.', AcmeShipmentQuery::class, AcmeShipment::class));
    });

    it('points at the provider as a way out', function () {
        isolateGraphQL();
        discoverGraphQL(AcmeShipmentQuery::class, AcmeMetaProvider::class)->apply();

        expect(fn() => GraphQL::schema())->toThrow(LogicException::class, 'A type provider can map the class to a type with TypeDefinition(class:).');
    });

    it('fails again when GraphQL is resolved a second time', function () {
        isolateGraphQL();
        discoverGraphQL(AcmeShipmentQuery::class, AcmeMetaProvider::class)->apply();

        expect(fn() => app(RebingGraphQL::class))->toThrow(LogicException::class)
            ->and(fn() => app(RebingGraphQL::class))->toThrow(LogicException::class, 'which is not a registered GraphQL output type');
    });

    it('passes once a provider maps the class', function () {
        isolateGraphQL();
        discoverGraphQL(AcmeShipmentQuery::class, AcmeShipmentProvider::class)->apply();

        expect(GraphQL::schema()->getQueryType()?->hasField('shipment'))->toBeTrue();
    });
});
