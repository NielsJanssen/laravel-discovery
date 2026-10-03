<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use Illuminate\Support\Facades\Gate;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\LaravelValidationRules;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\RuleProvider;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\RuleProviderRegistry;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredAction;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredFlattenedInput;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\GraphQLDiscovery;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Position;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRegistry;
use NielsJanssen\Laravel\Validation\RuleCompiler;
use RuntimeException;
use Tempest\Discovery\DiscoveryItems;
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

/** Discover, pass the items through serialize() as the discovery cache does, and apply them. */
function applyCachedAsArgs(string ...$classes): DiscoveryItems
{
    isolateGraphQL();
    $items = unserialize(serialize(discoverGraphQL(...$classes)->getItems()));

    if (! $items instanceof DiscoveryItems) {
        throw new RuntimeException('Items did not survive serialization.');
    }

    isolateGraphQL();
    $discovery = app(GraphQLDiscovery::class);
    $discovery->setItems($items);
    $discovery->apply();

    return $items;
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

        $items = applyCachedAsArgs(...PLACE_ORDER_SOURCES);

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
        isolateGraphQL();
        app()->instance('config_loaded_from_cache', true);

        $discovery = discoverGraphQL(...PLACE_ORDER_SOURCES);
        $discovery->apply();

        $registry = app(TypeRegistry::class);

        foreach ($discovery->getItems() as $item) {
            if ($item instanceof DiscoveredType) {
                expect(app()->bound((string) $item->bindName))->toBe($item->class !== AsArgs\PlaceOrder::class);
            }

            if ($item instanceof DiscoveredAction) {
                expect(app()->bound((string) $item->bindName))->toBeTrue();
            }
        }

        expect($registry->nameOf(AsArgs\Destination::class, Position::Input))->toBe('DestinationInput')
            ->and($registry->has(AsArgs\Shelf::class))->toBeTrue()
            ->and($registry->has(AsArgs\PlaceOrder::class))->toBeFalse()
            ->and($registry->typeNamed('PlaceOrderInput'))->toBeNull()
            ->and(config('graphql.types'))->toBe([]);

        app()->forgetInstance('config_loaded_from_cache');
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
    it('rejects them at discovery', function (array $classes, string $message) {
        expect(fn() => discoverGraphQL(...$classes))->toThrow(LogicException::class, $message);
    })->with([
        'a class without #[Input]' => [
            [Invalid\NotAnInput::class],
            '#[AsArgs] on the parameter $filter in ' . Invalid\NotAnInput::class . '::search needs an #[Input] class, but $filter is typed ' . Invalid\PlainFilter::class . '. Add #[Input] to PlainFilter, or remove #[AsArgs].',
        ],
        'a scalar' => [
            [Invalid\ScalarAsArgs::class],
            '#[AsArgs] on the parameter $term in ' . Invalid\ScalarAsArgs::class . '::search is not supported: $term is of type string, and #[AsArgs] only applies to a parameter typed as an #[Input] class. Remove #[AsArgs].',
        ],
        'an enum' => [
            [Invalid\EnumAsArgs::class],
            '#[AsArgs] on the parameter $shelf in ' . Invalid\EnumAsArgs::class . '::search is not supported: $shelf is the enum ' . AsArgs\Shelf::class . ', and #[AsArgs] only applies to a parameter typed as an #[Input] class. Remove #[AsArgs].',
        ],
        'a model' => [
            [Invalid\ModelAsArgs::class],
            '#[AsArgs] on the parameter $user in ' . Invalid\ModelAsArgs::class . '::search is not supported: $user is the Eloquent model ' . User::class . ', which binds by ID, and #[AsArgs] only applies to a parameter typed as an #[Input] class. Remove #[AsArgs].',
        ],
        'an injection' => [
            [Invalid\InjectionAsArgs::class],
            '#[AsArgs] on the parameter $info in ' . Invalid\InjectionAsArgs::class . '::search is not supported: $info is an injected value (#[Root], #[Context] or ResolveInfo), and #[AsArgs] only applies to a parameter typed as an #[Input] class. Remove #[AsArgs].',
        ],
        '#[Authorize] on the #[AsArgs] parameter' => [
            [Invalid\AuthorizeAsArgs::class],
            "#[AsArgs] on the parameter \$search in " . Invalid\AuthorizeAsArgs::class . "::search cannot be combined with #[Authorize]: the parameter binds no record of its own. Put #[Authorize('ability')] on the model property of the #[Input] class instead.",
        ],
        'a collision with a parameter' => [
            [Invalid\CollidesWithParameter::class],
            '#[AsArgs] on the parameter $search in ' . Invalid\CollidesWithParameter::class . '::search flattens ' . AsArgs\BookSearch::class . '::$term into the arg "term", which collides with the arg of the parameter $term. Rename the field with #[Field(name: ...)], or rename the other arg.',
        ],
        'a collision with a model binding' => [
            [Invalid\CollidesWithModelBinding::class],
            '#[AsArgs] on the parameter $search in ' . Invalid\CollidesWithModelBinding::class . '::search flattens ' . AsArgs\BookSearch::class . '::$year into the arg "year", which collides with the arg of the model-bound parameter $owner. Rename the field with #[Field(name: ...)], or rename the other arg.',
        ],
        'a collision with an arg provider' => [
            [Invalid\CollidesWithProvider::class],
            '#[AsArgs] on the parameter $cursor in ' . Invalid\CollidesWithProvider::class . '::pages flattens ' . Invalid\PageCursor::class . '::$page into the arg "page", which collides with the arg #[Paginated] adds. Rename the field with #[Field(name: ...)], or rename the other arg.',
        ],
        'two colliding #[AsArgs]' => [
            [Invalid\TwoCollidingAsArgs::class],
            '#[AsArgs] on the parameter $right in ' . Invalid\TwoCollidingAsArgs::class . '::compare flattens ' . AsArgs\BookSearch::class . '::$term into the arg "term", which collides with ' . AsArgs\BookSearch::class . '::$term, flattened by #[AsArgs] on $left. Rename the field with #[Field(name: ...)], or rename the other arg.',
        ],
        'a #[Field] method' => [
            [Invalid\FieldMethodAsArgs::class],
            'Method ' . Invalid\FieldMethodAsArgs::class . '::matches() has #[AsArgs] on $search, which #[Field] methods do not support yet: field args are neither validated, hydrated nor authorized. Take scalar args instead, or move the operation to a #[Query] or #[Mutation].',
        ],
        '#[AsArgs] with #[Arg]' => [
            [Invalid\AsArgsWithArg::class],
            '#[AsArgs] on the parameter $search in ' . Invalid\AsArgsWithArg::class . '::search cannot be combined with #[Arg]: the parameter has no arg of its own to name or describe. Remove #[Arg], and rename or describe the fields with #[Field(name:, description:)] on the #[Input] class.',
        ],
        'a nullable parameter' => [
            [Invalid\NullableAsArgs::class],
            '#[AsArgs] on the parameter $search in ' . Invalid\NullableAsArgs::class . '::search is not supported on a parameter that is nullable or has a default: flattened args cannot say the input as a whole is absent. Make the parameter required, or drop #[AsArgs] to take a nullable input arg.',
        ],
        'a parameter with a default' => [
            [Invalid\DefaultedAsArgs::class],
            '#[AsArgs] on the parameter $search in ' . Invalid\DefaultedAsArgs::class . '::search is not supported on a parameter that is nullable or has a default: flattened args cannot say the input as a whole is absent. Make the parameter required, or drop #[AsArgs] to take a nullable input arg.',
        ],
    ]);

    it('rejects a flattened field whose input type is not registered', function (bool $cached) {
        isolateGraphQL();

        if ($cached) {
            app()->instance('config_loaded_from_cache', true);
        }

        expect(fn() => discoverGraphQL(AsArgs\OrderMutations::class)->apply())->toThrow(
            LogicException::class,
            'Property ' . AsArgs\PlaceOrder::class . '::$shipTo, flattened into method ' . AsArgs\OrderMutations::class . '::placeOrder, references ' . AsArgs\Destination::class . ', which is not a registered GraphQL input type. Use a scalar, an enum or an #[Input] class, or name a registered GraphQL input type with #[Field(type: ...)].',
        );

        app()->forgetInstance('config_loaded_from_cache');
    })->with([
        'config written' => [false],
        'config cached' => [true],
    ]);
});
