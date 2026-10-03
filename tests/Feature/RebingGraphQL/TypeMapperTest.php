<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use GraphQL\Utils\SchemaPrinter;
use Illuminate\Support\ServiceProvider;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorization;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredAction;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\GraphQLDiscovery;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\GraphQLDiscoveryServiceProvider;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\Member;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\MemberKind;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\ScalarMap;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\TypeMapper;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\TypeMapperRegistry;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Position;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRef;
use Rebing\GraphQL\Support\Facades\GraphQL;
use RuntimeException;
use Tempest\Reflection\TypeReflector;
use Tests\Fixtures\RebingGraphQL\ContainerService;
use Tests\Fixtures\RebingGraphQL\Enums;
use Tests\Fixtures\RebingGraphQL\Mappers;

const MAPPED_SCALARS = ['Money' => Mappers\MoneyScalar::class, 'DateTime' => Mappers\DateTimeScalar::class];

function idsAreIds(): TypeMapper
{
    return closureMapper(static fn(TypeReflector $type, Member $member) => $member->name === 'id' && in_array($type->getName(), ['int', 'string'], true)
        ? TypeRef::named('ID', nullable: $type->isNullable())
        : null);
}

function idsAreInts(): TypeMapper
{
    return closureMapper(static fn(TypeReflector $type, Member $member) => $member->name === 'id' ? TypeRef::scalar('Int') : null);
}

function moneyMapper(): TypeMapper
{
    return closureMapper(static fn(TypeReflector $type) => $type->matches(Mappers\Money::class) ? TypeRef::named('Money') : null);
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
        refreshMappers();

        $date = new TypeReflector(CarbonImmutable::class);
        $member = new Member('listedAt', Mappers\Listing::class, Position::Output, MemberKind::Property);

        expect(app(TypeMapperRegistry::class)->map($date, $member))->toEqual(TypeRef::named('DateTime'));

        refreshMappers(greedyMapper());

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

    it('does not infer ID for an id without a mapper', function () {
        expect(schemaSdlWith(MAPPED_SCALARS, Mappers\PlainIdQuery::class))->toContain('find(id: Int!): Int!');

        refreshMappers(idsAreIds());

        expect(schemaSdlWith(MAPPED_SCALARS, Mappers\PlainIdQuery::class))->toContain('find(id: ID!): Int!');
    });

    it('lets the first mapper that answers win, in tag order', function (array $mappers, TypeRef $expected) {
        refreshMappers(...$mappers);

        $ref = app(TypeMapperRegistry::class)->map(
            new TypeReflector('int'),
            new Member('id', Mappers\Product::class, Position::Output, MemberKind::Property),
        );

        expect($ref)->toEqual($expected);
    })->with([
        'ID first' => fn() => [[idsAreIds(), idsAreInts()], TypeRef::named('ID')],
        'Int first' => fn() => [[idsAreInts(), idsAreIds()], TypeRef::scalar('Int')],
        'a null answer passes on' => fn() => [[moneyMapper(), idsAreInts()], TypeRef::scalar('Int')],
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
    beforeEach(fn() => refreshMappers(idsAreIds(), moneyMapper()));

    it('maps an id to ID and a Money value object to a custom scalar, on fields, field args, action args and returns', function () {
        expect(schemaSdlWith(MAPPED_SCALARS, Mappers\ProductQuery::class, Mappers\Product::class))
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
        expect(schemaSdlWith(MAPPED_SCALARS, Mappers\ExplicitOverMapperQuery::class, Mappers\ExplicitOverMapper::class))
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
        refreshMappers(closureMapper(static fn(TypeReflector $type, Member $member) => $member->kind === MemberKind::MethodReturn && $member->name === 'prices'
            ? TypeRef::named('Money', list: true, nullableItems: true)
            : null));

        expect(schemaSdlWith(MAPPED_SCALARS, Mappers\PriceListQuery::class))->toContain('prices: [Money]!');

        $this->postJson('/graphql', ['query' => '{ prices }'])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertExactJson(['data' => ['prices' => ['1.00 EUR', null]]]);
    });

    it('resolves a mapped arg and return end to end', function () {
        schemaSdlWith(MAPPED_SCALARS, Mappers\ProductQuery::class, Mappers\Product::class);

        $this->postJson('/graphql', ['query' => '{ price(amount: "12.5 USD") product(id: "7") { id price discount total(shipping: "2.25 EUR") related(id: "8") } }'])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertExactJson(['data' => [
                'price' => '12.50 USD',
                'product' => ['id' => '7', 'price' => '12.50 EUR', 'discount' => null, 'total' => '14.75 EUR', 'related' => '8'],
            ]]);
    });

    it('keeps mapped types through the discovery cache, without the mappers', function () {
        $items = cachedGraphQLItems(Mappers\ProductQuery::class, Mappers\Product::class);

        $action = array_find(
            iterator_to_array($items, false),
            static fn(mixed $item): bool => $item instanceof DiscoveredAction && $item->method === 'price',
        );

        expect($action)->toBeInstanceOf(DiscoveredAction::class)
            ->and($action->args[0]->type)->toEqual(TypeRef::named('Money'))
            ->and($action->returnType)->toEqual(TypeRef::named('Money'));

        app()->instance(TypeMapperRegistry::class, new TypeMapperRegistry());
        app()->forgetInstance(GraphQLDiscovery::class);
        applyGraphQLItems($items, MAPPED_SCALARS);

        expect(SchemaPrinter::doPrint(GraphQL::schema()))->toContain(PRODUCT_SDL)->toContain(PRODUCT_QUERY_SDL);
    });
});

describe('nullability', function () {
    beforeEach(fn() => refreshMappers(
        idsAreIds(),
        moneyMapper(),
        closureMapper(static fn(TypeReflector $type, Member $member) => $member->name === 'notes' ? TypeRef::scalar('String', nullable: true) : null),
    ));

    it('only ever widens: a nullable PHP type, #[Field(nullable:)] or a nullable mapping each make it nullable', function () {
        expect(schemaSdlWith(MAPPED_SCALARS, Mappers\TicketQuery::class, Mappers\Ticket::class))
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
        expectRejected(
            new class {
                #[Query]
                public function annotate(string $notes): string
                {
                    return $notes;
                }
            },
            'A type mapper makes the argument $notes in %1$s::annotate nullable (String), but the parameter accepts no null. Make the parameter nullable or give it a default.',
        );
    });

    it('keeps a mapper non-null when nothing widens it', function () {
        $discovered = discoveredTypes(Mappers\Product::class)[0];

        expect($discovered->fields[1]->type)->toEqual(TypeRef::named('Money'))
            ->and($discovered->fields[2]->type)->toEqual(TypeRef::named('Money', nullable: true));
    });
});

describe('what mappers never claim', function () {
    it('leaves model bindings, container injections, value objects and resolver injections alone', function () {
        refreshMappers(greedyMapper());

        $action = discoveredActions(Mappers\NotPreemptedQuery::class)['owner'];

        expect($action->modelBindings)->toHaveCount(1)
            ->and($action->modelBindings[0]->paramName)->toBe('user')
            ->and($action->containerInjections)->toBe(['service' => ContainerService::class])
            ->and($action->argCompositions)->toBe(['auth' => Authorization::class])
            ->and($action->injections)->toBe(['root' => 'root', 'context' => 'context', 'info' => 'info'])
            ->and(array_map(static fn($arg) => [$arg->paramName, $arg->type], $action->args))->toEqual([['note', TypeRef::scalar('String')]])
            ->and($action->returnType)->toEqual(TypeRef::scalar('String'));

        expect(schemaSdlWith(MAPPED_SCALARS, Mappers\NotPreemptedQuery::class))->toContain('owner(note: String!, id: ID!): String!');
    });

    it('leaves a class-typed parameter without #[Arg] to the container', function () {
        refreshMappers(moneyMapper());

        $action = discoveredActions(Mappers\ContainerMoneyQuery::class)['charge'];

        expect($action->containerInjections)->toBe(['amount' => Mappers\Money::class])
            ->and($action->args)->toHaveCount(1)
            ->and($action->args[0]->paramName)->toBe('other')
            ->and($action->args[0]->type)->toEqual(TypeRef::named('Money'));
    });

    it('still reports an untyped or mixed member, even with a mapper that claims everything', function (object $shape, string $format, string $exception) {
        refreshMappers(greedyMapper());

        expectRejected($shape, $format, exception: $exception);
    })->with([
        'an untyped property' => [
            fn() => new #[Type] class {
                public $loose;
            },
            'Property %1$s::$loose declares no type.',
            LogicException::class,
        ],
        'a mixed return' => [
            fn() => new class {
                #[Query]
                public function whatever(): mixed
                {
                    return 'x';
                }
            },
            'Method %1$s::whatever declares the type mixed.',
            RuntimeException::class,
        ],
    ]);

    it('hands static and union types to the mapper unresolved, and self as PHP reports it', function () {
        $seen = [];
        refreshMappers(closureMapper(static function (TypeReflector $type, Member $member) use (&$seen): TypeRef {
            $seen[$member->name] = $type;

            return TypeRef::scalar('String');
        }));

        discoverGraphQL(Mappers\Linked::class);

        expect($seen['itself']->getName())->toBe('static')
            ->and($seen['next']->getName())->toBe(Mappers\Linked::class)
            ->and($seen['next']->isNullable())->toBeTrue()
            ->and($seen['code']->isUnion())->toBeTrue()
            ->and($seen['code']->split())->toHaveCount(2);
    });
});

describe('enums a mapper points at', function () {
    it('registers an enum that only a mapped field and a mapped arg reference', function () {
        refreshMappers(closureMapper(static fn(TypeReflector $type, Member $member) => $member->name === 'tier' ? TypeRef::class(Enums\Mood::class) : null));

        expect(schemaSdlWith(MAPPED_SCALARS, Mappers\TierQuery::class, Mappers\Customer::class))
            ->toContain(<<<'GRAPHQL'
                enum Mood {
                  Calm
                  Cheerful
                  Gloomy
                }
                GRAPHQL)
            ->toContain(<<<'GRAPHQL'
                type Customer {
                  tier: Mood!
                }
                GRAPHQL)
            ->toContain('tierOf(tier: Mood!): String!');

        $this->postJson('/graphql', ['query' => '{ tierOf(tier: Cheerful) }'])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.tierOf', Enums\Mood::class . '::Cheerful');
    });
});

describe('ScalarMap', function () {
    beforeEach(function () {
        config()->set(ScalarMap::CONFIG, [CarbonInterface::class => 'DateTime']);
        refreshMappers();
    });

    it('serves a list action whose item type the scalar map names', function () {
        expect(schemaSdlWith(MAPPED_SCALARS, Mappers\ListingQuery::class, Mappers\Listing::class))->toContain('listedDays: [DateTime!]!');

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
        expect(schemaSdlWith(MAPPED_SCALARS, Mappers\ListingQuery::class, Mappers\Listing::class))
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
        schemaSdlWith(MAPPED_SCALARS, Mappers\ListingQuery::class, Mappers\Listing::class);

        $this->postJson('/graphql', ['query' => '{ dayAfter(date: "2026-01-02T03:04:05+00:00") listing { listedAt delistedAt } }'])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertExactJson(['data' => [
                'dayAfter' => '2026-01-03T03:04:05+00:00',
                'listing' => ['listedAt' => '2026-01-02T03:04:05+00:00', 'delistedAt' => null],
            ]]);
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
