<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Facades\Gate;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\LaravelValidationRules;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\RuleProvider;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\RuleProviderRegistry;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\AsArgs as AsArgsAttribute;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredFlattenedInput;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Paginated;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Pagination;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Position;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use NielsJanssen\Laravel\Validation\RuleCompiler;
use Tests\Fixtures\RebingGraphQL\Inputs\AsArgs;
use Tests\Fixtures\RebingGraphQL\Inputs\AsArgs\Invalid;
use Workbench\App\Models\User;

/** The order fixtures, in discovery order. */
const PLACE_ORDER_SOURCES = [AsArgs\OrderMutations::class, AsArgs\PlaceOrder::class, AsArgs\Destination::class];

/**
 * @param  array<string, mixed>  $variables
 * @return array{query: string, variables: array<string, mixed>}
 */
function placeOrderRequest(array $variables): array
{
    return [
        'query' => 'mutation ($channel: String!, $heading: String!, $shelf: Shelf!, $supplier: ID!, $buyer: ID!, $shipTo: DestinationInput, $copies: Int, $note: String, $wrapping: String) {
            placeOrder(channel: $channel, heading: $heading, shelf: $shelf, supplier: $supplier, buyer: $buyer, shipTo: $shipTo, copies: $copies, note: $note, wrapping: $wrapping)
        }',
        'variables' => $variables,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function placeOrderVariables(User $supplier, User $buyer, array $overrides = []): array
{
    return [
        'channel' => 'web',
        'heading' => 'Dune',
        'shelf' => 'Fiction',
        'supplier' => $supplier->id,
        'buyer' => $buyer->id,
        ...$overrides,
    ];
}

beforeEach(function () {
    AsArgs\OrderMutations::$received = null;
    AsArgs\OrderMutations::$channel = null;
    AsArgs\SearchQueries::$search = null;
    AsArgs\SearchQueries::$sorting = null;
    AsArgs\ShipmentMutations::$received = null;
});

describe('the flattened schema', function () {
    it('prints the fields of an #[AsArgs] input as top-level args, and no input type for it', function () {
        $definitions = sdlDefinitions(schemaSdl(AsArgs\SearchQueries::class, AsArgs\BookSearch::class, AsArgs\Sorting::class));

        expect($definitions)->toBe([
            <<<'GRAPHQL'
                enum Shelf {
                  Fiction
                  Poetry
                }
                GRAPHQL,
            <<<'GRAPHQL'
                type Query {
                  findBooks(term: String, shelf: Shelf, year: Int): String!
                  sortedBooks(term: String, shelf: Shelf, year: Int, sortBy: String = "title", descending: Boolean = false): String!
                }
                GRAPHQL,
        ]);
    });

    it('keeps renames, descriptions, deprecations, defaults, model keys and nested inputs', function () {
        $definitions = sdlDefinitions(schemaSdl(...PLACE_ORDER_SOURCES));

        expect($definitions)->toContain(
            <<<'GRAPHQL'
                type Mutation {
                  placeOrder(
                    channel: String!

                    "Printed on the slip"
                    heading: String!

                    shelf: Shelf!
                    supplier: ID!
                    buyer: ID!
                    shipTo: DestinationInput
                    copies: Int = 1
                    note: String @deprecated(reason: "Use shipTo")
                    wrapping: String = "plain"
                  ): String!
                }
                GRAPHQL,
            <<<'GRAPHQL'
                input DestinationInput {
                  street: String!
                  city: String!
                  courier: ID
                }
                GRAPHQL,
        )->and(implode("\n", $definitions))->not->toContain('PlaceOrderInput');
    });

    it('emits the input type when the class is also used as an input arg', function () {
        $definitions = sdlDefinitions(schemaSdl(AsArgs\SearchQueries::class, AsArgs\SavedSearches::class, AsArgs\BookSearch::class, AsArgs\Sorting::class));

        expect($definitions)->toContain(
            <<<'GRAPHQL'
                input BookSearchInput {
                  term: String
                  shelf: Shelf
                  year: Int
                }
                GRAPHQL,
            <<<'GRAPHQL'
                type Mutation {
                  saveSearch(search: BookSearchInput!): String!
                }
                GRAPHQL,
        )->and(implode("\n", $definitions))->not->toContain('SortingInput');
    });

    it('classifies the parameter as a flattened input, not as an arg or a container injection', function () {
        $action = discoveredActions(AsArgs\SearchQueries::class)['sortedBooks'];

        expect($action->args)->toBe([])
            ->and($action->containerInjections)->toBe([])
            ->and(array_map(static fn(DiscoveredFlattenedInput $flattened): array => [$flattened->paramName, $flattened->type->class], $action->flattenedInputs))->toBe([
                ['search', AsArgs\BookSearch::class],
                ['sorting', AsArgs\Sorting::class],
            ]);
    });
});

describe('hydration', function () {
    it('hydrates the input from the top-level args, with enums and defaults', function () {
        schemaSdl(AsArgs\SearchQueries::class, AsArgs\BookSearch::class, AsArgs\Sorting::class);

        $this->postJson('/graphql', ['query' => '{ findBooks(term: "dune", shelf: Poetry, year: 1965) }'])
            ->assertOk()
            ->assertExactJson(['data' => ['findBooks' => 'dune']]);

        expect(AsArgs\SearchQueries::$search)->toEqual(new AsArgs\BookSearch('dune', AsArgs\Shelf::Poetry, 1965));

        $this->postJson('/graphql', ['query' => '{ findBooks }'])
            ->assertOk()
            ->assertExactJson(['data' => ['findBooks' => 'all']]);

        expect(AsArgs\SearchQueries::$search)->toEqual(new AsArgs\BookSearch());
    });

    it('resolves a class flattened in one field and taken as an input arg in another', function () {
        schemaSdl(AsArgs\SearchQueries::class, AsArgs\SavedSearches::class, AsArgs\BookSearch::class, AsArgs\Sorting::class);

        $this->postJson('/graphql', ['query' => '{ findBooks(term: "dune", shelf: Fiction) }'])
            ->assertOk()
            ->assertExactJson(['data' => ['findBooks' => 'dune']]);

        expect(AsArgs\SearchQueries::$search)->toEqual(new AsArgs\BookSearch('dune', AsArgs\Shelf::Fiction));

        $this->postJson('/graphql', ['query' => 'mutation { saveSearch(search: { term: "emma", year: 1815 }) }'])
            ->assertOk()
            ->assertExactJson(['data' => ['saveSearch' => 'emma']]);

        $response = $this->postJson('/graphql', ['query' => 'mutation { saveSearch(search: { year: 99 }) } '])->assertOk();

        expect($response->json('errors.0.extensions.validation'))->toBe([
            'search.year' => ['The search.year field must be between 1450 and 2100.'],
        ]);
    });

    it('hydrates a nested input with a renamed field under a flattened arg', function () {
        schemaSdl(AsArgs\ShipmentMutations::class, AsArgs\Shipment::class, AsArgs\Parcel::class);

        $this->postJson('/graphql', ['query' => 'mutation { ship(label: "books", parcel: { weightKg: 12 }) }'])
            ->assertOk()
            ->assertExactJson(['data' => ['ship' => 12]]);

        expect(AsArgs\ShipmentMutations::$received)->toEqual(new AsArgs\Shipment('books', new AsArgs\Parcel(12)));

        $response = $this->postJson('/graphql', ['query' => 'mutation { ship(label: "books", parcel: { weightKg: 40 }) }'])->assertOk();

        expect($response->json('errors.0.extensions.validation'))->toBe([
            'parcel.weightKg' => ['A parcel weighs 30 kg at most.'],
        ]);
    });

    it('drops the messages of nested rule paths an input provider leaves out', function () {
        $compiled = app(RuleCompiler::class)->forValues(AsArgs\Shipment::class, ['label' => 'x', 'parcel' => new AsArgs\Parcel(40)]);

        expect($compiled->messages)->toHaveKey('parcel.weight.max');

        $set = app(LaravelValidationRules::class)->rulesForInput(AsArgs\Shipment::class, ['label' => 'x', 'parcel' => new AsArgs\Parcel(40)]);

        expect(array_keys($set->rules))->toBe(['label'])
            ->and($set->messages)->toBe(['label.min' => 'A label needs two letters.']);
    });

    it('hydrates two flattened inputs, each from only its own args', function () {
        schemaSdl(AsArgs\SearchQueries::class, AsArgs\BookSearch::class, AsArgs\Sorting::class);

        $this->postJson('/graphql', ['query' => '{ sortedBooks(term: "dune", descending: true) }'])
            ->assertOk()
            ->assertExactJson(['data' => ['sortedBooks' => 'title desc']]);

        expect(AsArgs\SearchQueries::$search)->toEqual(new AsArgs\BookSearch('dune'))
            ->and(AsArgs\SearchQueries::$sorting)->toEqual(new AsArgs\Sorting('title', true));
    });

    describe('with models', function () {
        beforeEach(function () {
            $this->loadLaravelMigrations();
            Gate::define('stock', fn(?User $actor, User $supplier) => true);
            Gate::define('deliver', fn(?User $actor, User $courier) => true);
        });

        it('maps renamed args back, binds the models and hydrates the nested input', function () {
            $supplier = User::factory()->create(['name' => 'Gollancz']);
            $buyer = User::factory()->create(['name' => 'Ada']);
            $courier = User::factory()->create(['name' => 'Speedy']);
            schemaSdl(...PLACE_ORDER_SOURCES);

            $this->postJson('/graphql', placeOrderRequest(placeOrderVariables($supplier, $buyer, [
                'shelf' => 'Poetry',
                'shipTo' => ['street' => 'Main 1', 'city' => 'Leiden', 'courier' => $courier->id],
                'copies' => 2,
                'note' => 'Fragile',
                'wrapping' => 'gift',
            ])))
                ->assertOk()
                ->assertJsonMissingPath('errors')
                ->assertExactJson(['data' => ['placeOrder' => 'Dune']]);

            $order = AsArgs\OrderMutations::$received;

            expect(AsArgs\OrderMutations::$channel)->toBe('web')
                ->and($order)->toBeInstanceOf(AsArgs\PlaceOrder::class)
                ->and($order->title)->toBe('Dune')
                ->and($order->shelf)->toBe(AsArgs\Shelf::Poetry)
                ->and($order->supplier->is($supplier))->toBeTrue()
                ->and($order->buyer->is($buyer))->toBeTrue()
                ->and($order->shipTo?->street)->toBe('Main 1')
                ->and($order->shipTo?->courier?->is($courier))->toBeTrue()
                ->and($order->copies)->toBe(2)
                ->and($order->remark)->toBe('Fragile')
                ->and($order->wrapping)->toBe('gift');
        });

        it('keeps the defaults of absent args, and of a non-nullable property sent as null', function () {
            $supplier = User::factory()->create();
            $buyer = User::factory()->create();
            schemaSdl(...PLACE_ORDER_SOURCES);

            $this->postJson('/graphql', placeOrderRequest(placeOrderVariables($supplier, $buyer, ['wrapping' => null])))
                ->assertOk()
                ->assertJsonMissingPath('errors');

            $order = AsArgs\OrderMutations::$received;

            expect($order?->wrapping)->toBe('plain')
                ->and($order?->copies)->toBe(1)
                ->and($order?->shipTo)->toBeNull()
                ->and($order?->remark)->toBeNull();
        });
    });
});

describe('validation on flattened fields', function () {
    beforeEach(function () {
        $this->loadLaravelMigrations();
        Gate::define('stock', fn(?User $actor, User $supplier) => true);
        Gate::define('deliver', fn(?User $actor, User $courier) => true);
    });

    it('reports a laravel-validation rule at the top-level arg name', function () {
        schemaSdl(AsArgs\SearchQueries::class, AsArgs\BookSearch::class, AsArgs\Sorting::class);

        $response = $this->postJson('/graphql', ['query' => '{ findBooks(year: 99) }'])
            ->assertOk()
            ->assertJsonPath('errors.0.message', 'validation');

        expect($response->json('errors.0.extensions.validation'))->toBe([
            'year' => ['The year field must be between 1450 and 2100.'],
        ])->and(AsArgs\SearchQueries::$search)->toBeNull();
    });

    it('asks a tagged InputRuleProvider about a flattened input, reporting at the arg name', function () {
        app()->tag([AsArgs\SearchTermRules::class], RuleProvider::TAG);
        app()->forgetInstance(RuleProviderRegistry::class);
        schemaSdl(AsArgs\SearchQueries::class, AsArgs\BookSearch::class, AsArgs\Sorting::class);

        $response = $this->postJson('/graphql', ['query' => '{ findBooks(term: "ab") }'])->assertOk();

        expect($response->json('errors.0.extensions.validation'))->toBe([
            'term' => ['Search for three letters or more.'],
        ])->and(AsArgs\SearchQueries::$search)->toBeNull();

        $this->postJson('/graphql', ['query' => '{ findBooks(term: "abc") }'])
            ->assertOk()
            ->assertExactJson(['data' => ['findBooks' => 'abc']]);
    });

    it('reports custom messages, #[Field(rules:)], the exists rule and nested rules at their arg paths', function () {
        $supplier = User::factory()->create();
        $buyer = User::factory()->create();
        schemaSdl(...PLACE_ORDER_SOURCES);

        $response = $this->postJson('/graphql', placeOrderRequest([
            ...placeOrderVariables($supplier, $buyer, [
                'heading' => 'D',
                'shelf' => 'Poetry',
                'copies' => 5,
                'shipTo' => ['street' => 'Main 1', 'city' => 'L'],
            ]),
            'buyer' => 999999,
        ]))->assertOk();

        expect($response->json('errors.0.extensions.validation'))->toEqual([
            'heading' => ['A heading needs two letters.'],
            'buyer' => ['The selected buyer is invalid.'],
            'copies' => ['The copies field must not be greater than 3.'],
            'shipTo.city' => ['The ship to.city field must be at least 3 characters.'],
        ])->and(AsArgs\OrderMutations::$received)->toBeNull();
    });

    it('evaluates a #[Field(rules:)] closure against the sibling flattened values', function () {
        $supplier = User::factory()->create();
        $buyer = User::factory()->create();
        schemaSdl(...PLACE_ORDER_SOURCES);

        $this->postJson('/graphql', placeOrderRequest(placeOrderVariables($supplier, $buyer, ['copies' => 5])))
            ->assertOk()
            ->assertJsonMissingPath('errors');

        expect(AsArgs\OrderMutations::$received?->copies)->toBe(5);
    });
});

describe('#[Authorize] on a flattened model property', function () {
    beforeEach(function () {
        $this->loadLaravelMigrations();
        Gate::define('deliver', fn(?User $actor, User $courier) => true);
    });

    it('checks the bound record before validation, reporting Forbidden', function () {
        $supplier = User::factory()->create(['name' => 'Gollancz']);
        $buyer = User::factory()->create();
        Gate::define('stock', fn(?User $actor, User $subject) => $subject->name !== 'Gollancz');
        schemaSdl(...PLACE_ORDER_SOURCES);

        $this->postJson('/graphql', placeOrderRequest(placeOrderVariables($supplier, $buyer, ['heading' => 'D'])))
            ->assertOk()
            ->assertJsonPath('errors.0.message', 'Forbidden')
            ->assertJsonPath('errors.0.extensions.category', 'authorization')
            ->assertJsonPath('data.placeOrder', null);

        expect(AsArgs\OrderMutations::$received)->toBeNull();
    });

    it('denies a record that does not exist', function () {
        $buyer = User::factory()->create();
        Gate::define('stock', fn(?User $actor) => true);
        schemaSdl(...PLACE_ORDER_SOURCES);

        $this->postJson('/graphql', placeOrderRequest([...placeOrderVariables($buyer, $buyer), 'supplier' => 999999]))
            ->assertOk()
            ->assertJsonPath('errors.0.message', 'Forbidden');

        expect(AsArgs\OrderMutations::$received)->toBeNull();
    });

    it('checks a model inside a nested input reached through a flattened property', function () {
        $supplier = User::factory()->create();
        $buyer = User::factory()->create();
        $courier = User::factory()->create(['name' => 'Slowpoke']);
        Gate::define('stock', fn(?User $actor) => true);
        Gate::define('deliver', fn(?User $actor, User $subject) => $subject->name !== 'Slowpoke');
        schemaSdl(...PLACE_ORDER_SOURCES);

        $this->postJson('/graphql', placeOrderRequest(placeOrderVariables($supplier, $buyer, [
            'shipTo' => ['street' => 'Main 1', 'city' => 'L', 'courier' => $courier->id],
        ])))
            ->assertOk()
            ->assertJsonPath('errors.0.message', 'Not a courier')
            ->assertJsonPath('data.placeOrder', null);

        expect(AsArgs\OrderMutations::$received)->toBeNull();
    });

    it('passes once every ability allows its record', function () {
        $supplier = User::factory()->create();
        $buyer = User::factory()->create();
        $courier = User::factory()->create();
        Gate::define('stock', fn(?User $actor, User $subject) => $subject->is($supplier));
        schemaSdl(...PLACE_ORDER_SOURCES);

        $this->postJson('/graphql', placeOrderRequest(placeOrderVariables($supplier, $buyer, [
            'shipTo' => ['street' => 'Main 1', 'city' => 'Leiden', 'courier' => $courier->id],
        ])))
            ->assertOk()
            ->assertExactJson(['data' => ['placeOrder' => 'Dune']]);
    });
});

describe('the discovery cache', function () {
    it('round-trips an action with a flattened input', function () {
        $action = discoveredActions(...PLACE_ORDER_SOURCES)['placeOrder'];

        expect(unserialize(serialize($action)))->toEqual($action)
            ->and($action->flattenedInputs[0]->type->class)->toBe(AsArgs\PlaceOrder::class)
            ->and($action->flattenedInputs[0]->type->fields[2]->binding?->modelClass)->toBe(User::class)
            ->and($action->flattenedInputs[0]->type->fields[5]->hasRules)->toBeTrue();
    });

    it('resolves, validates and authorizes from serialized items', function () {
        $this->loadLaravelMigrations();
        Gate::define('stock', fn(?User $actor) => true);
        $supplier = User::factory()->create();
        $buyer = User::factory()->create();

        $items = applyCachedGraphQL(PLACE_ORDER_SOURCES);

        $kept = [];

        foreach ($items as $item) {
            if ($item instanceof DiscoveredType && $item->class !== AsArgs\PlaceOrder::class) {
                $kept[$item->name] = $item->bindName;
            }
        }

        expect(config('graphql.types'))->toEqualCanonicalizing($kept)
            ->and(array_keys($kept))->toEqualCanonicalizing(['Shelf', 'DestinationInput']);

        $this->postJson('/graphql', placeOrderRequest(placeOrderVariables($supplier, $buyer)))
            ->assertOk()
            ->assertExactJson(['data' => ['placeOrder' => 'Dune']]);

        $this->postJson('/graphql', placeOrderRequest(placeOrderVariables($supplier, $buyer, ['heading' => 'D'])))
            ->assertOk()
            ->assertJsonPath('errors.0.extensions.validation.heading', ['A heading needs two letters.']);

        Gate::define('stock', fn(?User $actor) => false);

        $this->postJson('/graphql', placeOrderRequest(placeOrderVariables($supplier, $buyer)))
            ->assertOk()
            ->assertJsonPath('errors.0.message', 'Forbidden');
    });

    it('registers what the flattened fields use, and not the flattened class, when the configuration is cached', function () {
        $registry = assertBoundWhenConfigCached(PLACE_ORDER_SOURCES, fn(DiscoveredType $type) => $type->class !== AsArgs\PlaceOrder::class);

        expect($registry->nameOf(AsArgs\Destination::class, Position::Input))->toBe('DestinationInput')
            ->and($registry->has(AsArgs\Shelf::class))->toBeTrue()
            ->and($registry->has(AsArgs\PlaceOrder::class))->toBeFalse()
            ->and($registry->typeNamed('PlaceOrderInput'))->toBeNull();
    });

    it('registers an enum only a flattened field references, without the input class being discovered', function () {
        $definitions = sdlDefinitions(schemaSdl(AsArgs\SearchQueries::class));

        expect($definitions)->toContain(
            <<<'GRAPHQL'
                enum Shelf {
                  Fiction
                  Poetry
                }
                GRAPHQL,
        );
    });
});

describe('rejected shapes', function () {
    it('rejects them at discovery', function (object $shape, string $format) {
        expectRejected($shape, $format);
    })->with([
        'a class without #[Input]' => [
            fn() => new class {
                #[Query]
                public function search(#[AsArgsAttribute] Invalid\PlainFilter $filter): string
                {
                    return 'never';
                }
            },
            '#[AsArgs] on the parameter $filter in %1$s::search needs an #[Input] class, but $filter is typed ' . Invalid\PlainFilter::class . '. Add #[Input] to PlainFilter, or remove #[AsArgs].',
        ],
        'a scalar' => [
            fn() => new class {
                #[Query]
                public function search(#[AsArgsAttribute] string $term): string
                {
                    return $term;
                }
            },
            '#[AsArgs] on the parameter $term in %1$s::search is not supported: $term is of type string, and #[AsArgs] only applies to a parameter typed as an #[Input] class. Remove #[AsArgs].',
        ],
        'an enum' => [
            fn() => new class {
                #[Query]
                public function search(#[AsArgsAttribute] AsArgs\Shelf $shelf): string
                {
                    return $shelf->name;
                }
            },
            '#[AsArgs] on the parameter $shelf in %1$s::search is not supported: $shelf is the enum ' . AsArgs\Shelf::class . ', and #[AsArgs] only applies to a parameter typed as an #[Input] class. Remove #[AsArgs].',
        ],
        'a model' => [
            fn() => new class {
                #[Query]
                public function search(#[AsArgsAttribute] User $user): string
                {
                    return $user->name;
                }
            },
            '#[AsArgs] on the parameter $user in %1$s::search is not supported: $user is the Eloquent model ' . User::class . ', which binds by ID, and #[AsArgs] only applies to a parameter typed as an #[Input] class. Remove #[AsArgs].',
        ],
        'an injection' => [
            fn() => new class {
                #[Query]
                public function search(#[AsArgsAttribute] ResolveInfo $info): string
                {
                    return $info->fieldName;
                }
            },
            '#[AsArgs] on the parameter $info in %1$s::search is not supported: $info is an injected value (#[Root], #[Context] or ResolveInfo), and #[AsArgs] only applies to a parameter typed as an #[Input] class. Remove #[AsArgs].',
        ],
        '#[Authorize] on the #[AsArgs] parameter' => [
            fn() => new class {
                #[Query]
                public function search(#[AsArgsAttribute, Authorize('view')] AsArgs\BookSearch $search): string
                {
                    return 'never';
                }
            },
            "#[AsArgs] on the parameter \$search in %1\$s::search cannot be combined with #[Authorize]: the parameter binds no record of its own. Put #[Authorize('ability')] on the model property of the #[Input] class instead.",
        ],
        'a collision with a parameter' => [
            fn() => new class {
                #[Query]
                public function search(#[AsArgsAttribute] AsArgs\BookSearch $search, string $term): string
                {
                    return $term;
                }
            },
            '#[AsArgs] on the parameter $search in %1$s::search flattens ' . AsArgs\BookSearch::class . '::$term into the arg "term", which collides with the arg of the parameter $term. Rename the field with #[Field(name: ...)], or rename the other arg.',
        ],
        'a collision with a model binding' => [
            fn() => new class {
                #[Query]
                public function search(#[Arg('year')] User $owner, #[AsArgsAttribute] AsArgs\BookSearch $search): string
                {
                    return 'never';
                }
            },
            '#[AsArgs] on the parameter $search in %1$s::search flattens ' . AsArgs\BookSearch::class . '::$year into the arg "year", which collides with the arg of the model-bound parameter $owner. Rename the field with #[Field(name: ...)], or rename the other arg.',
        ],
        'a collision with an arg provider' => [
            fn() => new class {
                #[Query(type: 'String')]
                #[Paginated]
                public function pages(#[AsArgsAttribute] Invalid\PageCursor $cursor, Pagination $pagination): array
                {
                    return [];
                }
            },
            '#[AsArgs] on the parameter $cursor in %1$s::pages flattens ' . Invalid\PageCursor::class . '::$page into the arg "page", which collides with the arg #[Paginated] adds. Rename the field with #[Field(name: ...)], or rename the other arg.',
        ],
        'two colliding #[AsArgs]' => [
            fn() => new class {
                #[Query]
                public function compare(#[AsArgsAttribute] AsArgs\BookSearch $left, #[AsArgsAttribute] AsArgs\BookSearch $right): string
                {
                    return 'never';
                }
            },
            '#[AsArgs] on the parameter $right in %1$s::compare flattens ' . AsArgs\BookSearch::class . '::$term into the arg "term", which collides with ' . AsArgs\BookSearch::class . '::$term, flattened by #[AsArgs] on $left. Rename the field with #[Field(name: ...)], or rename the other arg.',
        ],
        'a #[Field] method' => [
            fn() => new #[Type] class {
                #[Field]
                public function matches(#[AsArgsAttribute] AsArgs\BookSearch $search): int
                {
                    return 0;
                }
            },
            'Method %1$s::matches() has #[AsArgs] on $search, which #[Field] methods do not support yet: field args are neither validated, hydrated nor authorized. Take scalar args instead, or move the operation to a #[Query] or #[Mutation].',
        ],
        '#[AsArgs] with #[Arg]' => [
            fn() => new class {
                #[Query]
                public function search(#[AsArgsAttribute, Arg('filter')] AsArgs\BookSearch $search): string
                {
                    return 'never';
                }
            },
            '#[AsArgs] on the parameter $search in %1$s::search cannot be combined with #[Arg]: the parameter has no arg of its own to name or describe. Remove #[Arg], and rename or describe the fields with #[Field(name:, description:)] on the #[Input] class.',
        ],
        'a nullable parameter' => [
            fn() => new class {
                #[Query]
                public function search(#[AsArgsAttribute] ?AsArgs\BookSearch $search = null): string
                {
                    return 'never';
                }
            },
            '#[AsArgs] on the parameter $search in %1$s::search is not supported on a parameter that is nullable or has a default: flattened args cannot say the input as a whole is absent. Make the parameter required, or drop #[AsArgs] to take a nullable input arg.',
        ],
        'a parameter with a default' => [
            fn() => new class {
                #[Query]
                public function search(#[AsArgsAttribute] AsArgs\BookSearch $search = new AsArgs\BookSearch()): string
                {
                    return 'never';
                }
            },
            '#[AsArgs] on the parameter $search in %1$s::search is not supported on a parameter that is nullable or has a default: flattened args cannot say the input as a whole is absent. Make the parameter required, or drop #[AsArgs] to take a nullable input arg.',
        ],
    ]);

    it('rejects a flattened field whose input type is not registered', function () {
        isolateGraphQL();

        expect(fn() => discoverGraphQL(AsArgs\OrderMutations::class)->apply())->toThrow(
            LogicException::class,
            'Property ' . AsArgs\PlaceOrder::class . '::$shipTo, flattened into method ' . AsArgs\OrderMutations::class . '::placeOrder, references ' . AsArgs\Destination::class . ', which is not a registered GraphQL input type. Use a scalar, an enum or an #[Input] class, or name a registered GraphQL input type with #[Field(type: ...)].',
        );
    });
});
