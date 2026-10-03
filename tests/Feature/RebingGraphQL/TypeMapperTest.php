<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use GraphQL\Utils\SchemaPrinter;
use Illuminate\Support\ServiceProvider;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorization;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredArg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\GraphQLDiscovery;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\GraphQLDiscoveryServiceProvider;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\Member;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\MemberKind;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\ScalarMap;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\TypeMapper;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\TypeMapperRegistry;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Position;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRef;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Reflection\TypeReflector;
use Tests\Fixtures\RebingGraphQL\ContainerService;
use Tests\Fixtures\RebingGraphQL\Mappers;

/** Tag type mappers, discarding the registry so the new tags and the current scalar map take effect. */
function tagTypeMappers(string ...$mappers): void
{
    app()->tag($mappers, TypeMapper::TAG);
    app()->forgetInstance(TypeMapperRegistry::class);
    app()->forgetInstance(GraphQLDiscovery::class);
}

/** Print the default schema with the Money and DateTime scalars registered by hand. */
function mappedSchemaSdl(string ...$classes): string
{
    isolateGraphQL();
    config()->set('graphql.types', ['Money' => Mappers\MoneyScalar::class, 'DateTime' => Mappers\DateTimeScalar::class]);
    discoverGraphQL(...$classes)->apply();

    return SchemaPrinter::doPrint(GraphQL::schema());
}

/** Apply discovery items, as they come back from the discovery cache, with the scalars registered. */
function applyMappedItems(DiscoveryItems $items): string
{
    isolateGraphQL();
    config()->set('graphql.types', ['Money' => Mappers\MoneyScalar::class, 'DateTime' => Mappers\DateTimeScalar::class]);

    $discovery = app(GraphQLDiscovery::class);
    $discovery->setItems($items);
    $discovery->apply();

    return SchemaPrinter::doPrint(GraphQL::schema());
}

const PRODUCT_SDL = <<<'GRAPHQL'
    type Product {
      id: ID!
      price: Money!
      discount: Money
      total(shipping: Money!): Money!
      related(id: ID): String!
    }
    GRAPHQL;

const PRODUCT_QUERY_SDL = <<<'GRAPHQL'
    type Query {
      product(id: ID!): Product!
      price(amount: Money!): Money!
      cheapest: Money
    }
    GRAPHQL;

describe('the contract', function () {
    it('ships an empty scalar map and tags no mappers', function () {
        expect(config('discovery.graphql.scalars'))->toBe([])
            ->and(iterator_to_array(app()->tagged(TypeMapper::TAG), false))->toBe([]);
    });

    it('asks ScalarMap after the tagged mappers, so a consumer mapper wins', function () {
        config()->set(ScalarMap::CONFIG, [CarbonInterface::class => 'DateTime']);
        tagTypeMappers();

        $date = new TypeReflector(CarbonImmutable::class);
        $member = new Member('listedAt', Mappers\Listing::class, Position::Output, MemberKind::Property);

        expect(app(TypeMapperRegistry::class)->map($date, $member))->toEqual(TypeRef::named('DateTime'));

        tagTypeMappers(Mappers\GreedyMapper::class);

        expect(app(TypeMapperRegistry::class)->map($date, $member))->toEqual(TypeRef::scalar('String'));
    });

    it('publishes the config under discovery-graphql-config', function () {
        expect(ServiceProvider::pathsToPublish(GraphQLDiscoveryServiceProvider::class, 'discovery-graphql-config'))
            ->toHaveCount(1)
            ->toContain(config_path('discovery-graphql.php'));
    });

    it('reads a published config/discovery-graphql.php into discovery.graphql', function () {
        config()->set('discovery.graphql', null);
        config()->set('discovery-graphql', ['scalars' => [CarbonInterface::class => 'DateTime']]);

        (new GraphQLDiscoveryServiceProvider(app()))->register();

        expect(config('discovery.graphql.scalars'))->toBe([CarbonInterface::class => 'DateTime']);
    });

    it('resolves the type of an arg cached before typeRef existed', function () {
        $current = serialize(new DiscoveredArg('mood', 'mood', 'string', false));
        $stale = str_replace([':11:{', 's:7:"typeRef";N;'], [':10:{', ''], $current);

        $arg = unserialize($stale);

        expect($stale)->not->toBe($current)
            ->and($arg)->toBeInstanceOf(DiscoveredArg::class)
            ->and($arg->ref())->toEqual(TypeRef::scalar('string'));
    });

    it('does not infer ID for an id without a mapper', function () {
        expect(mappedSchemaSdl(Mappers\PlainIdQuery::class))->toContain('find(id: Int!): Int!');

        tagTypeMappers(Mappers\IdsAreIds::class);

        expect(mappedSchemaSdl(Mappers\PlainIdQuery::class))->toContain('find(id: ID!): Int!');
    });

    it('lets the first mapper that answers win, in tag order', function (array $mappers, string $expected) {
        tagTypeMappers(...$mappers);

        $ref = app(TypeMapperRegistry::class)->map(
            new TypeReflector('int'),
            new Member('id', Mappers\Product::class, Position::Output, MemberKind::Property),
        );

        expect($ref)->toEqual($expected === 'ID' ? TypeRef::named('ID') : TypeRef::scalar($expected));
    })->with([
        'ID first' => [[Mappers\IdsAreIds::class, Mappers\IdsAreInts::class], 'ID'],
        'Int first' => [[Mappers\IdsAreInts::class, Mappers\IdsAreIds::class], 'Int'],
        'a null answer passes on' => [[Mappers\MoneyMapper::class, Mappers\IdsAreInts::class], 'Int'],
    ]);

    it('rejects a scalar map entry that is not a class-string to a type name', function () {
        config()->set(ScalarMap::CONFIG, [CarbonInterface::class => 3]);

        app(ScalarMap::class)->map(
            new TypeReflector(Mappers\Money::class),
            new Member('price', Mappers\Product::class, Position::Output, MemberKind::Property),
        );
    })->throws(LogicException::class, 'Config discovery.graphql.scalars maps class-strings to GraphQL type names');
});

describe('mapped types', function () {
    beforeEach(fn() => tagTypeMappers(Mappers\IdsAreIds::class, Mappers\MoneyMapper::class));

    it('maps an id to ID and a Money value object to a custom scalar, on fields, field args, action args and returns', function () {
        expect(mappedSchemaSdl(Mappers\ProductQuery::class, Mappers\Product::class))
            ->toContain(PRODUCT_SDL)
            ->toContain(PRODUCT_QUERY_SDL)
            ->toContain(<<<'GRAPHQL'
                type Mutation {
                  discount(amount: Money!, id: ID): String!
                }
                GRAPHQL);

        buildAllSchemas();
    });

    it('lets an explicit type: or of: beat a mapper on #[Field], #[Arg] and #[Query]', function () {
        expect(mappedSchemaSdl(Mappers\ExplicitOverMapperQuery::class, Mappers\ExplicitOverMapper::class))
            ->toContain(<<<'GRAPHQL'
                type ExplicitOverMapper {
                  id: String!
                  instalments: [Int!]!
                  cents(id: Int!): Int!
                }
                GRAPHQL)
            ->toContain('label(amount: String!): String!')
            ->toContain('labels: [String!]!');
    });

    it('takes a list from a mapper on an action return', function () {
        tagTypeMappers(Mappers\PricesAreLists::class);

        expect(mappedSchemaSdl(Mappers\PriceListQuery::class))->toContain('prices: [Money]!');

        $this->postJson('/graphql', ['query' => '{ prices }'])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertExactJson(['data' => ['prices' => ['1.00 EUR', null]]]);
    });

    it('resolves a mapped arg and return end to end', function () {
        mappedSchemaSdl(Mappers\ProductQuery::class, Mappers\Product::class);

        $this->postJson('/graphql', ['query' => '{ price(amount: "12.5 USD") product(id: "7") { id price discount total(shipping: "2.25 EUR") related(id: "8") } }'])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertExactJson(['data' => [
                'price' => '12.50 USD',
                'product' => ['id' => '7', 'price' => '12.50 EUR', 'discount' => null, 'total' => '14.75 EUR', 'related' => '8'],
            ]]);
    });

    it('round-trips a mapped action through serialize()', function () {
        $action = discoveredActions(Mappers\ProductQuery::class)['price'];

        expect(unserialize(serialize($action)))->toEqual($action)
            ->and($action->args[0]->typeRef)->toEqual(TypeRef::named('Money'))
            ->and($action->returnType)->toEqual(TypeRef::named('Money'));
    });

    it('keeps mapped types through the discovery cache, without the mappers', function () {
        $items = unserialize(serialize(discoverGraphQL(Mappers\ProductQuery::class, Mappers\Product::class)->getItems()));

        app()->instance(TypeMapperRegistry::class, new TypeMapperRegistry());
        app()->forgetInstance(GraphQLDiscovery::class);

        expect($items)->toBeInstanceOf(DiscoveryItems::class)
            ->and(applyMappedItems($items))->toContain(PRODUCT_SDL)->toContain(PRODUCT_QUERY_SDL);
    });
});

describe('nullability', function () {
    beforeEach(fn() => tagTypeMappers(Mappers\IdsAreIds::class, Mappers\MoneyMapper::class, Mappers\NullableNotes::class));

    it('only ever widens: a nullable PHP type, #[Field(nullable:)] or a nullable mapping each make it nullable', function () {
        expect(mappedSchemaSdl(Mappers\TicketQuery::class, Mappers\Ticket::class))
            ->toContain(<<<'GRAPHQL'
                type Ticket {
                  id: ID
                  notes: String
                  deposit: Money
                }
                GRAPHQL)
            ->toContain('ticket(notes: String): Ticket!');
    });

    it('rejects a nullable mapping for a parameter that accepts no null', function () {
        discoverGraphQL(Mappers\NonNullNotesQuery::class);
    })->throws(LogicException::class, 'A type mapper makes the argument $notes in Tests\Fixtures\RebingGraphQL\Mappers\NonNullNotesQuery::annotate nullable (String), but the parameter accepts no null. Make the parameter nullable or give it a default.');

    it('keeps a mapper non-null when nothing widens it', function () {
        $discovered = array_find(
            iterator_to_array(discoverGraphQL(Mappers\Product::class)->getItems(), false),
            static fn(mixed $item): bool => $item instanceof DiscoveredType,
        );

        expect($discovered)->toBeInstanceOf(DiscoveredType::class)
            ->and($discovered->fields[1]->type)->toEqual(TypeRef::named('Money'))
            ->and($discovered->fields[2]->type)->toEqual(TypeRef::named('Money', nullable: true));
    });
});

describe('what mappers never claim', function () {
    it('leaves model bindings, container injections, value objects and resolver injections alone', function () {
        tagTypeMappers(Mappers\GreedyMapper::class);

        $action = discoveredActions(Mappers\NotPreemptedQuery::class)['owner'];

        expect($action->modelBindings)->toHaveCount(1)
            ->and($action->modelBindings[0]->paramName)->toBe('user')
            ->and($action->containerInjections)->toBe(['service' => ContainerService::class])
            ->and($action->argCompositions)->toBe(['auth' => Authorization::class])
            ->and($action->injections)->toBe(['root' => 'root', 'context' => 'context', 'info' => 'info'])
            ->and(array_map(static fn($arg) => [$arg->paramName, $arg->ref()], $action->args))->toEqual([['note', TypeRef::scalar('String')]])
            ->and($action->returnType)->toEqual(TypeRef::scalar('String'));

        expect(mappedSchemaSdl(Mappers\NotPreemptedQuery::class))->toContain('owner(note: String!, id: ID!): String!');
    });

    it('leaves a class-typed parameter without #[Arg] to the container', function () {
        tagTypeMappers(Mappers\MoneyMapper::class);

        $action = discoveredActions(Mappers\ContainerMoneyQuery::class)['charge'];

        expect($action->containerInjections)->toBe(['amount' => Mappers\Money::class])
            ->and($action->args)->toHaveCount(1)
            ->and($action->args[0]->paramName)->toBe('other')
            ->and($action->args[0]->ref())->toEqual(TypeRef::named('Money'));
    });

    it('still reports an untyped or mixed member, even with a mapper that claims everything', function (string $class, string $message) {
        tagTypeMappers(Mappers\GreedyMapper::class);

        expect(fn() => discoverGraphQL($class))->toThrow($message);
    })->with([
        'an untyped property' => [Mappers\Untyped::class, 'Property Tests\Fixtures\RebingGraphQL\Mappers\Untyped::$loose declares no type.'],
        'an untyped return' => [Mappers\UntypedReturnQuery::class, 'Method Tests\Fixtures\RebingGraphQL\Mappers\UntypedReturnQuery::anything declares no type.'],
        'a mixed return' => [Mappers\MixedReturnQuery::class, 'Method Tests\Fixtures\RebingGraphQL\Mappers\MixedReturnQuery::whatever declares the type mixed.'],
    ]);

    it('hands static and union types to the mapper unresolved, and self as PHP reports it', function () {
        Mappers\RecordingMapper::$seen = [];
        tagTypeMappers(Mappers\RecordingMapper::class);

        discoverGraphQL(Mappers\Linked::class);

        $seen = Mappers\RecordingMapper::$seen;

        expect($seen['itself']->getName())->toBe('static')
            ->and($seen['next']->getName())->toBe(Mappers\Linked::class)
            ->and($seen['next']->isNullable())->toBeTrue()
            ->and($seen['code']->isUnion())->toBeTrue()
            ->and($seen['code']->split())->toHaveCount(2);
    });
});

describe('enums a mapper points at', function () {
    it('registers an enum that only a mapped field and a mapped arg reference', function () {
        tagTypeMappers(Mappers\TierCodes::class);

        expect(mappedSchemaSdl(Mappers\TierQuery::class, Mappers\Customer::class))
            ->toContain(<<<'GRAPHQL'
                enum Tier {
                  Gold
                  Silver
                }
                GRAPHQL)
            ->toContain(<<<'GRAPHQL'
                type Customer {
                  tier: Tier!
                }
                GRAPHQL)
            ->toContain('tierOf(tier: Tier!): String!');

        $this->postJson('/graphql', ['query' => '{ tierOf(tier: Silver) }'])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.tierOf', Mappers\Tier::class . '::Silver');
    });
});

describe('ScalarMap', function () {
    beforeEach(function () {
        config()->set(ScalarMap::CONFIG, [CarbonInterface::class => 'DateTime']);
        tagTypeMappers();
    });

    it('serves a list action whose item type the scalar map names', function () {
        expect(mappedSchemaSdl(Mappers\ListedDaysQuery::class))->toContain('listedDays: [DateTime!]!');

        buildAllSchemas();
    });

    it('uses the first matching entry', function (array $scalars, TypeRef $expected) {
        config()->set(ScalarMap::CONFIG, $scalars);

        expect(app(ScalarMap::class)->map(
            new TypeReflector(CarbonImmutable::class),
            new Member('listedAt', Mappers\Listing::class, Position::Output, MemberKind::Property),
        ))->toEqual($expected);
    })->with([
        'CarbonInterface first' => [[CarbonInterface::class => 'DateTime', \DateTimeInterface::class => 'String'], TypeRef::named('DateTime')],
        'DateTimeInterface first' => [[\DateTimeInterface::class => 'String', CarbonInterface::class => 'DateTime'], TypeRef::scalar('String')],
    ]);

    it('rejects a key that is neither a class nor an interface', function () {
        config()->set(ScalarMap::CONFIG, ['App\\Missing' => 'DateTime']);

        app(ScalarMap::class)->map(
            new TypeReflector(CarbonImmutable::class),
            new Member('listedAt', Mappers\Listing::class, Position::Output, MemberKind::Property),
        );
    })->throws(LogicException::class, 'Config discovery.graphql.scalars maps [App\Missing], which is not a class or interface.');

    it('reads the config once', function () {
        $map = app(ScalarMap::class);
        $type = new TypeReflector(CarbonImmutable::class);
        $member = new Member('listedAt', Mappers\Listing::class, Position::Output, MemberKind::Property);

        expect($map->map($type, $member))->toEqual(TypeRef::named('DateTime'));

        config()->set(ScalarMap::CONFIG, []);

        expect($map->map($type, $member))->toEqual(TypeRef::named('DateTime'));
    });

    it('matches a CarbonImmutable against a CarbonInterface entry, even for a type name that is also a PHP class', function () {
        expect(mappedSchemaSdl(Mappers\ListingQuery::class, Mappers\Listing::class))
            ->toContain(<<<'GRAPHQL'
                type Listing {
                  listedAt: DateTime!
                  delistedAt: DateTime
                }
                GRAPHQL)
            ->toContain('dayAfter(date: DateTime!): DateTime!');

        buildAllSchemas();
    });

    it('resolves a mapped date arg and return end to end', function () {
        mappedSchemaSdl(Mappers\ListingQuery::class, Mappers\Listing::class);

        $this->postJson('/graphql', ['query' => '{ dayAfter(date: "2026-01-02T03:04:05+00:00") listing { listedAt delistedAt } }'])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertExactJson(['data' => [
                'dayAfter' => '2026-01-03T03:04:05+00:00',
                'listing' => ['listedAt' => '2026-01-02T03:04:05+00:00', 'delistedAt' => null],
            ]]);
    });

    it('maps a scalar entry to the built-in scalar', function () {
        config()->set(ScalarMap::CONFIG, [CarbonInterface::class => 'String']);

        expect(app(ScalarMap::class)->map(
            new TypeReflector(CarbonImmutable::class),
            new Member('listedAt', Mappers\Listing::class, Position::Output, MemberKind::Property),
        ))->toEqual(TypeRef::scalar('String'));
    });

    it('ignores scalars, unions and unmapped classes', function (string $type) {
        expect(app(ScalarMap::class)->map(
            new TypeReflector($type),
            new Member('listedAt', Mappers\Listing::class, Position::Output, MemberKind::Property),
        ))->toBeNull();
    })->with([
        'a scalar' => ['string'],
        'a union' => [CarbonImmutable::class . '|string'],
        'an unmapped class' => [Mappers\Money::class],
    ]);
});
