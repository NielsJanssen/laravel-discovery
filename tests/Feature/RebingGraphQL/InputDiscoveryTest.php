<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Countable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Exists;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\HydratorRegistry;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\InputHydrator;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Denied;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredInputType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Ignore;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\Member;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\MemberKind;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\ScalarMap;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Position;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeKind;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRef;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRegistry;
use Rebing\GraphQL\Support\Type as RebingType;
use RuntimeException;
use Tests\Fixtures\RebingGraphQL\AlwaysAllowGate;
use Tests\Fixtures\RebingGraphQL\Inputs;
use Tests\Fixtures\RebingGraphQL\Inputs\Invalid;
use Tests\Fixtures\RebingGraphQL\Mappers;
use Workbench\App\Models\User;

/** The CreateBook fixtures, in discovery order. */
const CREATE_BOOK_SOURCES = [Inputs\BookMutations::class, Inputs\CreateBook::class, Inputs\Address::class, Inputs\Chapter::class];

/**
 * @return array<string, mixed>
 */
function createBookInput(int $publisher, array $overrides = []): array
{
    return [
        'title' => 'Dune',
        'genre' => 'Fiction',
        'publisher' => $publisher,
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $input
 */
function createBookRequest(array $input): array
{
    return [
        'query' => 'mutation ($input: CreateBookInput!) { createBook(input: $input) }',
        'variables' => ['input' => $input],
    ];
}

beforeEach(function () {
    Inputs\BookMutations::$received = null;
    Inputs\ProfileMutation::$received = null;
});

describe('the input schema', function () {
    it('prints an input with nested inputs, a list, an enum, a model key and a shared #[Type, Input] class', function () {
        $definitions = sdlDefinitions(schemaSdl(...CREATE_BOOK_SOURCES));

        expect($definitions)->toContain(
            <<<'GRAPHQL'
                input CreateBookInput {
                  title: String!
                  genre: Genre!
                  publisher: ID!
                  shipTo: AddressInput
                  chapters: [ChapterInput!] = []
                  publishAt: String
                }
                GRAPHQL,
            <<<'GRAPHQL'
                type Address {
                  street: String!
                  city: String!
                  country: String!
                }
                GRAPHQL,
            <<<'GRAPHQL'
                input AddressInput {
                  street: String!
                  city: String!
                  country: String!
                }
                GRAPHQL,
            <<<'GRAPHQL'
                input ChapterInput {
                  title: String!
                }
                GRAPHQL,
            <<<'GRAPHQL'
                type Mutation {
                  createBook(input: CreateBookInput!): String!
                  draftBook(
                    "Draft contents"
                    data: CreateBookInput
                  ): String!
                }
                GRAPHQL,
        );

        buildAllSchemas();

        $registry = app(TypeRegistry::class);

        expect($registry->nameOf(Inputs\Address::class, Position::Input))->toBe('AddressInput')
            ->and($registry->nameOf(Inputs\Address::class, Position::Output))->toBe('Address')
            ->and($registry->kindOf(Inputs\CreateBook::class, Position::Input))->toBe(TypeKind::Input)
            ->and($registry->has(Inputs\CreateBook::class, Position::Output))->toBeFalse();
    });

    it('names, describes, renames, defaults and deprecates input fields', function () {
        expect(sdlDefinitions(schemaSdl(Inputs\ProfileMutation::class, Inputs\Profile::class)))->toContain(<<<'GRAPHQL'
            "A renamed input"
            input ProfileFields {
              volume: Int = 5
              nickname: String!
              shades: [Shade!] = []
              shouted: String!

              "First and last name"
              fullName: String!
              shade: Shade = Dark @deprecated(reason: "Use shades")
            }
            GRAPHQL);

        buildAllSchemas();
    });

    it('keeps a class name that already ends in Input', function () {
        expect(schemaSdl(Inputs\FilterQuery::class, Inputs\FilterInput::class))
            ->toContain('input FilterInput {')
            ->toContain('search(filter: FilterInput): String!');
    });

    it('leaves out an input that nothing uses, with the enum only it references', function (array $sources) {
        $sdl = schemaSdl(...$sources);

        expect($sdl)->not->toContain('UnusedInput')
            ->not->toContain('enum Shade')
            ->toContain('input ChapterInput {')
            ->and(config('graphql.types'))->not->toHaveKey('UnusedInput')
            ->and(app(TypeRegistry::class)->has(Inputs\Unused::class))->toBeFalse();

        $unused = array_values(array_filter(discoveredTypesOf(TypeKind::Input, ...$sources), static fn(DiscoveredType $type): bool => $type->class === Inputs\Unused::class));

        expect($unused)->toHaveCount(1)
            ->and(app()->bound((string) $unused[0]->bindName))->toBeFalse();
    })->with([
        'unused first' => [[Inputs\Unused::class, ...CREATE_BOOK_SOURCES]],
        'unused last' => [[...CREATE_BOOK_SOURCES, Inputs\Unused::class]],
    ]);

    it('emits nothing for inputs when no action takes one', function () {
        expect(schemaSdl(Inputs\ClockQuery::class, Inputs\CreateBook::class, Inputs\Address::class, Inputs\Chapter::class))
            ->not->toContain('input ')
            ->toContain('type Address {');
    });
});

describe('a class that is both #[Type] and #[Input]', function () {
    it('reads a field-level #[Authorize] in output position only', function () {
        $types = [];

        foreach (discoverGraphQL(Inputs\Badge::class)->getItems() as $item) {
            if ($item instanceof DiscoveredType) {
                $types[$item->kind->value] = $item;
            }
        }

        expect(array_keys($types))->toBe(['object', 'input'])
            ->and($types['object']->fields[1]->decorators)->toHaveCount(1)
            ->and($types['input']->fields[1]->binding)->toBeNull()
            ->and($types['input']->name)->toBe('BadgeInput');
    });
});

describe('input args', function () {
    it('classifies an #[Input] parameter as an arg named after the parameter', function () {
        $actions = discoveredActions(Inputs\BookMutations::class);

        expect(array_map(static fn($arg) => [$arg->name, $arg->paramName, $arg->type, $arg->nullable, $arg->input], [...$actions['createBook']->args, ...$actions['draftBook']->args]))->toBe([
            ['input', 'input', Inputs\CreateBook::class, false, true],
            ['data', 'draft', Inputs\CreateBook::class, true, true],
        ])
            ->and($actions['draftBook']->args[0]->description)->toBe('Draft contents')
            ->and($actions['createBook']->containerInjections)->toBe([]);
    });

    it('still injects a class without #[Input] from the container', function () {
        $action = discoveredActions(Inputs\ClockQuery::class)['time'];

        expect($action->args)->toBe([])
            ->and($action->containerInjections)->toBe(['clock' => Inputs\Clock::class]);

        schemaSdl(Inputs\ClockQuery::class);

        $this->postJson('/graphql', ['query' => '{ time }'])
            ->assertOk()
            ->assertExactJson(['data' => ['time' => 'noon']]);
    });
});

describe('hydration', function () {
    beforeEach(function () {
        $this->loadLaravelMigrations();
        Gate::define('attach', fn(?User $actor, User $publisher) => true);
    });

    it('hydrates the input class, with nested inputs, a list, an enum case and the bound model', function () {
        $publisher = User::factory()->create(['name' => 'Chilton']);
        schemaSdl(...CREATE_BOOK_SOURCES);

        $this->postJson('/graphql', createBookRequest(createBookInput($publisher->id, [
            'shipTo' => ['street' => 'Main 1', 'city' => 'Leiden', 'country' => 'NL'],
            'chapters' => [['title' => 'One'], ['title' => 'Two']],
            'publishAt' => '1965-08-01',
        ])))
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertExactJson(['data' => ['createBook' => 'Dune']]);

        $book = Inputs\BookMutations::$received;

        expect($book)->toBeInstanceOf(Inputs\CreateBook::class)
            ->and($book->genre)->toBe(Inputs\Genre::Fiction)
            ->and($book->publisher)->toBeInstanceOf(User::class)
            ->and($book->publisher->is($publisher))->toBeTrue()
            ->and($book->shipTo)->toEqual(new Inputs\Address('Main 1', 'Leiden', 'NL'))
            ->and($book->chapters)->toEqual([new Inputs\Chapter('One'), new Inputs\Chapter('Two')])
            ->and($book->publishAt)->toBe('1965-08-01');
    });

    it('keeps the constructor defaults for absent optional fields', function () {
        $publisher = User::factory()->create();
        schemaSdl(...CREATE_BOOK_SOURCES);

        $this->postJson('/graphql', createBookRequest(createBookInput($publisher->id)))
            ->assertOk()
            ->assertJsonMissingPath('errors');

        expect(Inputs\BookMutations::$received?->shipTo)->toBeNull()
            ->and(Inputs\BookMutations::$received?->chapters)->toBe([])
            ->and(Inputs\BookMutations::$received?->publishAt)->toBeNull();
    });

    it('reads a renamed, nullable input arg, and passes null when it is absent', function () {
        $publisher = User::factory()->create();
        schemaSdl(...CREATE_BOOK_SOURCES);

        $this->postJson('/graphql', [
            'query' => 'mutation ($data: CreateBookInput) { draftBook(data: $data) }',
            'variables' => ['data' => createBookInput($publisher->id, ['title' => 'Draft'])],
        ])
            ->assertOk()
            ->assertExactJson(['data' => ['draftBook' => 'Draft']]);

        expect(Inputs\BookMutations::$received)->toBeInstanceOf(Inputs\CreateBook::class);

        $this->postJson('/graphql', ['query' => 'mutation { draftBook }'])
            ->assertOk()
            ->assertExactJson(['data' => ['draftBook' => 'empty']]);

        expect(Inputs\BookMutations::$received)->toBeNull();
    });

    it('maps renamed fields back, and sets properties the constructor does not take', function () {
        schemaSdl(Inputs\ProfileMutation::class, Inputs\Profile::class);

        $this->postJson('/graphql', ['query' => 'mutation { saveProfile(profile: { fullName: "Ada", nickname: "ada", shades: [Light, Dark], shouted: "hi", volume: 9 }) }'])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertExactJson(['data' => ['saveProfile' => 'Ada']]);

        $profile = Inputs\ProfileMutation::$received;

        expect($profile)->toBeInstanceOf(Inputs\Profile::class)
            ->and($profile->name)->toBe('Ada')
            ->and($profile->nickname)->toBe('ada')
            ->and($profile->volume)->toBe(9)
            ->and($profile->shades)->toBe([Inputs\Shade::Light, Inputs\Shade::Dark])
            ->and($profile->shade)->toBe(Inputs\Shade::Dark)
            ->and($profile->shouted)->toBe('HI')
            ->and($profile->secret)->toBe('kept');
    });

    it('keeps the default of a non-nullable property when its optional field is sent as null', function () {
        $publisher = User::factory()->create();
        schemaSdl(...CREATE_BOOK_SOURCES);

        $this->postJson('/graphql', createBookRequest(createBookInput($publisher->id, ['chapters' => null])))
            ->assertOk()
            ->assertJsonMissingPath('errors');

        expect(Inputs\BookMutations::$received?->chapters)->toBe([]);

        schemaSdl(Inputs\ProfileMutation::class, Inputs\Profile::class);

        $this->postJson('/graphql', ['query' => 'mutation { saveProfile(profile: { fullName: "Ada", nickname: "ada", shouted: "hi", volume: null }) }'])
            ->assertOk()
            ->assertJsonMissingPath('errors');

        expect(Inputs\ProfileMutation::$received?->volume)->toBe(5);
    });

    it('passes an object default through when the input arg is absent', function () {
        schemaSdl(Inputs\FilterQuery::class, Inputs\FilterInput::class);

        $this->postJson('/graphql', ['query' => '{ all: search given: search(filter: { term: "dune" }) blank: search(filter: {}) }'])
            ->assertOk()
            ->assertExactJson(['data' => ['all' => 'all', 'given' => 'dune', 'blank' => 'none']]);
    });

    it('builds enums from case names or cases, and refuses an unknown name', function () {
        $hydrator = new InputHydrator();

        expect($hydrator->hydrates(Inputs\Chapter::class))->toBeTrue()
            ->and($hydrator->hydrates(Inputs\Clock::class))->toBeFalse()
            ->and(app(HydratorRegistry::class)->hydrates(Inputs\Profile::class))->toBeTrue()
            ->and($hydrator->hydrate(Inputs\Profile::class, ['name' => 'Ada', 'shade' => 'Light', 'shades' => ['Dark', Inputs\Shade::Light, null]]))
            ->toMatchObject(['shade' => Inputs\Shade::Light, 'shades' => [Inputs\Shade::Dark, Inputs\Shade::Light, null]]);

        expect(fn() => $hydrator->hydrate(Inputs\Profile::class, ['name' => 'Ada', 'shade' => 'Grey']))
            ->toThrow(RuntimeException::class, 'Cannot hydrate ' . Inputs\Shade::class . ': it has no case named [Grey].');
    });
});

describe('validation on input fields', function () {
    beforeEach(function () {
        $this->loadLaravelMigrations();
        Gate::define('attach', fn(?User $actor, User $publisher) => true);
    });

    it('reports laravel-validation rules and #[Field(rules:)] at their nested paths', function () {
        $publisher = User::factory()->create();
        schemaSdl(...CREATE_BOOK_SOURCES);

        $response = $this->postJson('/graphql', createBookRequest(createBookInput($publisher->id, [
            'title' => 'D',
            'shipTo' => ['street' => 'Main 1', 'city' => 'L', 'country' => 'NLD'],
            'publishAt' => 'soon',
        ])))
            ->assertOk()
            ->assertJsonPath('errors.0.message', 'validation');

        expect(Inputs\BookMutations::$received)->toBeNull()
            ->and($response->json('errors.0.extensions.validation'))->toEqual([
                'input.title' => ['The input.title field must be at least 2 characters.'],
                'input.publishAt' => ['The input.publish at field must be a valid date.'],
                'input.shipTo.city' => ['The input.ship to.city field must be at least 3 characters.'],
                'input.shipTo.country' => ['The input.ship to.country field must be 2 characters.'],
            ]);
    });

    it('validates each item of a list of inputs at its index', function () {
        $publisher = User::factory()->create();
        schemaSdl(...[...CREATE_BOOK_SOURCES, Inputs\ReviewMutation::class, Inputs\ReviewBatch::class, Inputs\Review::class]);

        $response = $this->postJson('/graphql', [
            'query' => 'mutation { reviewAll(batch: { reviews: [{ body: "fine", stars: 1 }, { body: "short", stars: 5 }] }) }',
        ])->assertOk();

        expect($response->json('errors.0.extensions.validation'))->toBe([
            'batch.reviews.1.body' => ['The batch.reviews.1.body field must be at least 10 characters.'],
        ]);
    });

    it('evaluates a closure in #[Field(rules:)] against the sibling values', function () {
        schemaSdl(Inputs\ReviewMutation::class, Inputs\Review::class, Inputs\ReviewBatch::class);

        $this->postJson('/graphql', ['query' => 'mutation { review(review: { body: "ok", stars: 2 }) }'])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertExactJson(['data' => ['review' => 'anonymous']]);

        $response = $this->postJson('/graphql', ['query' => 'mutation { review(review: { body: "ok", stars: 5 }) }'])->assertOk();

        expect($response->json('errors.0.extensions.validation'))->toBe([
            'review.body' => ['The review.body field must be at least 10 characters.'],
        ]);
    });

    it('reports the custom message of a validation attribute at the nested path', function () {
        $publisher = User::factory()->create();
        schemaSdl(...CREATE_BOOK_SOURCES);

        $response = $this->postJson('/graphql', createBookRequest(createBookInput($publisher->id, [
            'chapters' => [['title' => 'One'], ['title' => 'X']],
        ])))->assertOk();

        expect($response->json('errors.0.extensions.validation'))->toBe([
            'input.chapters.1.title' => ['A chapter title needs two letters.'],
        ]);
    });

    it('adds an exists rule to a non-nullable model field only', function () {
        isolateGraphQL();
        discoverGraphQL(...[...CREATE_BOOK_SOURCES, Inputs\ReviewMutation::class, Inputs\Review::class, Inputs\ReviewBatch::class])->apply();

        $types = [];

        foreach (['CreateBookInput', 'ReviewInput'] as $name) {
            $types[$name] = app(TypeRegistry::class)->typeNamed($name)?->createType(app());
        }

        expect($types['CreateBookInput'])->toBeInstanceOf(DiscoveredInputType::class);

        $publisherRules = $types['CreateBookInput']->getFields()['publisher']['rules'](['publisher' => 1]);
        $reviewerRules = $types['ReviewInput']->getFields()['reviewer']['rules'](['reviewer' => 1, 'body' => 'x', 'stars' => 1]);

        expect($publisherRules)->toHaveCount(1)
            ->and($publisherRules[0])->toBeInstanceOf(Exists::class)
            ->and((string) $publisherRules[0])->toBe('exists:users,id')
            ->and($reviewerRules)->toBe([]);
    });
});

describe('#[Authorize] on a model-bound input property', function () {
    beforeEach(function () {
        $this->loadLaravelMigrations();
    });

    it('checks the bound record before validation, reporting Forbidden', function () {
        $publisher = User::factory()->create(['name' => 'Gollancz']);
        Gate::define('attach', fn(?User $actor, User $subject) => $subject->name !== 'Gollancz');
        schemaSdl(...CREATE_BOOK_SOURCES);

        $this->postJson('/graphql', createBookRequest(createBookInput($publisher->id, ['title' => 'D'])))
            ->assertOk()
            ->assertJsonPath('errors.0.message', 'Forbidden')
            ->assertJsonPath('errors.0.extensions.category', 'authorization')
            ->assertJsonPath('data.createBook', null);

        expect(Inputs\BookMutations::$received)->toBeNull();
    });

    it('denies a record that does not exist on a non-nullable property', function () {
        Gate::define('attach', fn(?User $actor) => true);
        schemaSdl(...CREATE_BOOK_SOURCES);

        $this->postJson('/graphql', createBookRequest(createBookInput(999999)))
            ->assertOk()
            ->assertJsonPath('errors.0.message', 'Forbidden');
    });

    it('passes once the ability allows the record', function () {
        $publisher = User::factory()->create();
        Gate::define('attach', fn(?User $actor, User $subject) => $subject->is($publisher));
        schemaSdl(...CREATE_BOOK_SOURCES);

        $this->postJson('/graphql', createBookRequest(createBookInput($publisher->id)))
            ->assertOk()
            ->assertJsonMissingPath('errors');
    });

    it('skips a nullable property that is absent or finds no record, and checks one that does', function () {
        $reviewer = User::factory()->create(['name' => 'Ada']);
        Gate::define('review', fn(?User $actor) => false);
        schemaSdl(Inputs\ReviewMutation::class, Inputs\Review::class, Inputs\ReviewBatch::class);

        $this->postJson('/graphql', ['query' => 'mutation { absent: review(review: { body: "ok", stars: 1 }) gone: review(review: { body: "ok", stars: 1, reviewer: 999999 }) }'])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertExactJson(['data' => ['absent' => 'anonymous', 'gone' => 'anonymous']]);

        $this->postJson('/graphql', ['query' => "mutation { review(review: { body: \"ok\", stars: 1, reviewer: {$reviewer->id} }) }"])
            ->assertOk()
            ->assertJsonPath('errors.0.message', 'Not a reviewer');

        Gate::define('review', fn(?User $actor) => true);

        $this->postJson('/graphql', ['query' => "mutation { review(review: { body: \"ok\", stars: 1, reviewer: {$reviewer->id} }) }"])
            ->assertOk()
            ->assertExactJson(['data' => ['review' => 'Ada']]);
    });

    it('checks every item of a list of inputs', function () {
        $allowed = User::factory()->create(['name' => 'Ada']);
        $denied = User::factory()->create(['name' => 'Eve']);
        Gate::define('review', fn(?User $actor, User $subject) => $subject->name === 'Ada');
        schemaSdl(Inputs\ReviewMutation::class, Inputs\Review::class, Inputs\ReviewBatch::class);

        $this->postJson('/graphql', ['query' => "mutation { reviewAll(batch: { reviews: [{ body: \"ok\", stars: 1, reviewer: {$allowed->id} }, { body: \"ok\", stars: 1, reviewer: {$denied->id} }] }) }"])
            ->assertOk()
            ->assertJsonPath('errors.0.message', 'Not a reviewer');

        $this->postJson('/graphql', ['query' => "mutation { reviewAll(batch: { reviews: [{ body: \"ok\", stars: 1, reviewer: {$allowed->id} }] }) }"])
            ->assertOk()
            ->assertExactJson(['data' => ['reviewAll' => 1]]);
    });
});

describe('#[Authorize] on an input reached through a raw arg', function () {
    beforeEach(function () {
        $this->loadLaravelMigrations();
        refreshMappers(Inputs\ReviewListMapper::class);
    });

    it('checks an input named by #[Arg(type:)] on an array parameter', function () {
        $publisher = User::factory()->create();
        Gate::define('attach', fn(?User $actor) => false);
        schemaSdl(...[...CREATE_BOOK_SOURCES, Inputs\RawInputMutations::class, Inputs\ReviewMutation::class, Inputs\Review::class, Inputs\ReviewBatch::class]);

        $request = [
            'query' => 'mutation ($raw: CreateBookInput!) { createRaw(raw: $raw) }',
            'variables' => ['raw' => createBookInput($publisher->id)],
        ];

        $this->postJson('/graphql', $request)
            ->assertOk()
            ->assertJsonPath('errors.0.message', 'Forbidden')
            ->assertJsonPath('data.createRaw', null);

        Gate::define('attach', fn(?User $actor) => true);

        $this->postJson('/graphql', $request)
            ->assertOk()
            ->assertExactJson(['data' => ['createRaw' => 'Dune']]);
    });

    it('checks every item of a list-typed raw arg', function () {
        $allowed = User::factory()->create(['name' => 'Ada']);
        $denied = User::factory()->create(['name' => 'Eve']);
        Gate::define('review', fn(?User $actor, User $subject) => $subject->name === 'Ada');
        schemaSdl(Inputs\RawInputMutations::class, Inputs\ReviewMutation::class, Inputs\Review::class, Inputs\ReviewBatch::class, ...CREATE_BOOK_SOURCES);

        $this->postJson('/graphql', ['query' => "mutation { reviewMany(reviews: [{ body: \"ok\", stars: 1, reviewer: {$allowed->id} }, { body: \"ok\", stars: 1, reviewer: {$denied->id} }]) }"])
            ->assertOk()
            ->assertJsonPath('errors.0.message', 'Not a reviewer')
            ->assertJsonPath('data.reviewMany', null);

        $this->postJson('/graphql', ['query' => "mutation { reviewMany(reviews: [{ body: \"ok\", stars: 1, reviewer: {$allowed->id} }]) }"])
            ->assertOk()
            ->assertExactJson(['data' => ['reviewMany' => '1 reviewed']]);
    });
});

describe('the discovery cache', function () {
    it('round-trips an input type whose field has closure rules and a model binding', function () {
        $review = array_find(discoveredTypesOf(TypeKind::Input, Inputs\Review::class), static fn(DiscoveredType $type): bool => $type->name === 'ReviewInput');

        expect(unserialize(serialize($review)))->toEqual($review)
            ->and($review->bindName)->toStartWith('discovery.rebing_graphql.type.')
            ->and($review->fields[0]->hasRules)->toBeTrue()
            ->and($review->fields[2]->binding?->modelClass)->toBe(User::class)
            ->and($review->fields[2]->binding?->nullable)->toBeTrue()
            ->and(array_map(static fn($authorize) => $authorize->ability, $review->fields[2]->binding->authorizations ?? []))->toBe(['review']);
    });

    it('writes the bind names of the cached items, so the config and the container agree', function () {
        $this->loadLaravelMigrations();
        Gate::define('attach', fn(?User $actor) => true);
        $publisher = User::factory()->create();

        $items = applyCachedGraphQL([Inputs\Unused::class, ...CREATE_BOOK_SOURCES]);

        $kept = [];

        foreach ($items as $item) {
            if ($item instanceof DiscoveredType && $item->class !== Inputs\Unused::class && $item->name !== 'Shade') {
                $kept[$item->name] = $item->bindName;
            }
        }

        expect(config('graphql.types'))->toEqualCanonicalizing($kept)
            ->and(array_keys($kept))->toEqualCanonicalizing(['CreateBookInput', 'Genre', 'Address', 'AddressInput', 'ChapterInput']);

        $this->postJson('/graphql', createBookRequest(createBookInput($publisher->id, ['chapters' => [['title' => 'One']]])))
            ->assertOk()
            ->assertJsonMissingPath('errors');

        Gate::define('attach', fn(?User $actor) => false);

        $this->postJson('/graphql', createBookRequest(createBookInput($publisher->id)))
            ->assertOk()
            ->assertJsonPath('errors.0.message', 'Forbidden');
    });

    it('binds the used inputs and fills the registry when the configuration is cached', function () {
        $registry = assertBoundWhenConfigCached(
            [Inputs\Unused::class, ...CREATE_BOOK_SOURCES],
            static fn(DiscoveredType $type): bool => $type->class !== Inputs\Unused::class && $type->name !== 'Shade',
        );

        expect($registry->nameOf(Inputs\CreateBook::class, Position::Input))->toBe('CreateBookInput')
            ->and($registry->nameOf(Inputs\Chapter::class, Position::Input))->toBe('ChapterInput')
            ->and($registry->has(Inputs\Unused::class))->toBeFalse()
            ->and($registry->has(Inputs\Shade::class))->toBeFalse()
            ->and($registry->typeNamed('CreateBookInput')?->class)->toBe(Inputs\CreateBook::class)
            ->and($registry->typeNamed('UnusedInput'))->toBeNull();
    });
});

describe('rejected shapes', function () {
    it('rejects them at discovery', function (object $shape, string $format) {
        expectRejected($shape, $format);
    })->with([
        'an interface property' => [
            fn() => new #[Input] class {
                public Countable $items;
            },
            'Property %1$s::$items references Countable, which is an interface, which has no GraphQL input type. Use a scalar, an enum or an #[Input] class, or name a registered GraphQL input type with #[Field(type: ...)].',
        ],
        'a union property' => [
            fn() => new #[Input] class {
                public int|string $value = 0;
            },
            'Property %1$s::$value has the union type string|int, which has no GraphQL input type. Use a single type, or name one with #[Field(type: ...)].',
        ],
        'a list of models' => [
            fn() => new #[Input] class {
                /** @var list<User> */
                #[Field(of: User::class)]
                public array $users = [];
            },
            'Property %1$s::$users references ' . User::class . ', which is an Eloquent model, which is only bound as a single ID; a list of models is not supported. Use a scalar, an enum or an #[Input] class, or name a registered GraphQL input type with #[Field(type: ...)].',
        ],
        'a builtin type with no input form' => [
            fn() => new #[Input] class {
                public object $anything;
            },
            'Property %1$s::$anything has type object, which has no GraphQL input type. Name one with #[Field(type: ...)].',
        ],
        '#[Authorize] on a property that binds no model' => [
            fn() => new #[Input] class {
                #[Authorize('view')]
                public string $title = '';
            },
            '#[Authorize] on property %1$s::$title only applies to a property that binds an Eloquent model; $title does not.',
        ],
        '#[Authorize] without an ability' => [
            fn() => new #[Input] class {
                #[Authorize]
                public User $owner;
            },
            "#[Authorize] on property %1\$s::\$owner needs an ability, as in #[Authorize('view')], to check the record it binds.",
        ],
        '#[Authorize(gate:)] on a property' => [
            fn() => new #[Input] class {
                #[Authorize('view', gate: AlwaysAllowGate::class)]
                public User $owner;
            },
            '#[Authorize(gate:)] on property %1$s::$owner is not supported: a gate class receives the raw args, so it belongs on the action.',
        ],
        '#[Authorize(onDenied:)] on a property' => [
            fn() => new #[Input] class {
                #[Authorize('view', onDenied: Denied::Error)]
                public User $owner;
            },
            '#[Authorize(onDenied:)] on property %1$s::$owner only applies to a field of a #[Type]. A denied input always reports an error; remove onDenied:.',
        ],
        'a #[Field] method on an input' => [
            fn() => new #[Input] class {
                public string $title = '';

                #[Field]
                public function shout(): string
                {
                    return strtoupper($this->title);
                }
            },
            'Method %1$s::shout() has #[Field], but an #[Input] takes its fields from properties only. Make it a property, or add #[Type] to ',
        ],
        'two fields with one name' => [
            fn() => new #[Input] class {
                public string $title = '';

                #[Field(name: 'title')]
                public string $heading = '';
            },
            'Input %1$s has two fields named "title" ($title and $heading). Rename one with #[Field(name: ...)], or #[Ignore] one.',
        ],
        '#[Field] with #[Ignore]' => [
            fn() => new #[Input] class {
                #[Field, Ignore]
                public string $title = '';
            },
            'Property %1$s::$title has both #[Field] and #[Ignore]. Remove one.',
        ],
        '#[Field] on a private property' => [
            fn() => new #[Input] class {
                #[Field]
                private string $title = '';

                public function title(): string
                {
                    return $this->title;
                }
            },
            'Property %1$s::$title has #[Field] but is not public. Only public, non-static properties become fields.',
        ],
        '#[Field] on a property without a set hook' => [
            fn() => new #[Input] class {
                #[Field]
                public string $title {
                    get => 'fixed';
                }
            },
            'Property %1$s::$title has #[Field] but no set hook, so an input cannot fill it.',
        ],
        'one name for #[Type] and #[Input]' => [
            fn() => new #[Type(name: 'Same'), Input(name: 'Same')] class {
                public string $title = '';
            },
            'GraphQL type name [Same] is used by both #[Type] and #[Input] on %1$s. Rename one with #[Input(name: ...)].',
        ],
        '#[Arg(type:)] on an input parameter' => [
            fn() => new class {
                #[Mutation]
                public function addChapter(#[Arg(type: 'ChapterInput')] Inputs\Chapter $chapter): string
                {
                    return $chapter->title;
                }
            },
            '#[Arg(type:)] on the parameter $chapter in %1$s::addChapter is not supported: its type is the input type of the #[Input] Chapter. Remove type:.',
        ],
        'an input parameter on a #[Field] method' => [
            fn() => new #[Type] class {
                #[Field]
                public function matches(Inputs\Chapter $chapter): bool
                {
                    return $chapter->title !== '';
                }
            },
            'Method %1$s::matches() takes the #[Input] Chapter as $chapter, which fields do not support yet. Take its values as scalar args instead.',
        ],
        '#[Field(rules:)] on an output-only property' => [
            fn() => new #[Type] class {
                #[Field(rules: ['min:2'])]
                public string $title = '';
            },
            'Property %1$s::$title has #[Field(rules:)], but rules only apply to a property of an #[Input]. Add #[Input] to the class, or remove rules:.',
        ],
        'a constructor parameter no field fills' => [
            fn() => new #[Input] class ('x', 'y') {
                public string $token;

                public function __construct(public string $title, string $secret)
                {
                    $this->token = hash('sha256', $secret);
                }
            },
            'Constructor parameter $secret of the #[Input] %1$s has no default and no input field to fill it from, so the input cannot be built. Make it a public promoted property, give it a default, or make it nullable.',
        ],
        'a required constructor property made optional' => [
            fn() => new #[Input] readonly class ('x') {
                public function __construct(
                    #[Field(nullable: true)]
                    public string $title,
                ) {}
            },
            'Property %1$s::$title is optional in the input (through a property default, #[Field(nullable: true)] or a type mapper), but its constructor parameter has no default and accepts no null, so an absent value cannot build the input. Give $title a default, make it nullable, or keep the field required.',
        ],
        '#[Field(rules:)] on a method' => [
            fn() => new #[Type(), Input] class {
                public string $title = '';

                #[Field(rules: ['min:2'])]
                public function shout(): string
                {
                    return strtoupper($this->title);
                }
            },
            'Method %1$s::shout() has #[Field(rules:)], but rules only apply to a property of an #[Input]. Remove rules:.',
        ],
    ]);

    it('rejects the shapes that need a named class at discovery', function (array $classes, string $message) {
        expect(fn() => discoverGraphQL(...$classes))->toThrow(LogicException::class, $message);
    })->with([
        'an output-only #[Type] property' => [
            [Invalid\OutputTypeProperty::class],
            'Property ' . Invalid\OutputTypeProperty::class . '::$thing references ' . Invalid\OutputOnly::class . ', which is an output-only #[Type]. Add #[Input] to OutputOnly to accept it as input too. Use a scalar, an enum or an #[Input] class, or name a registered GraphQL input type with #[Field(type: ...)].',
        ],
        '#[Input] on an enum' => [
            [Invalid\InputEnum::class],
            '#[Input] on the enum ' . Invalid\InputEnum::class . ' is not supported: an enum is not an input object type. Use the enum as a property or parameter type instead.',
        ],
        '#[Input] on an abstract class' => [
            [Invalid\AbstractInput::class],
            '#[Input] on ' . Invalid\AbstractInput::class . ', which cannot be instantiated: an input is hydrated into a new instance. Make it a concrete class with a public constructor.',
        ],
        '#[Input] on a Rebing type' => [
            [Invalid\RebingInput::class],
            '#[Input] on ' . Invalid\RebingInput::class . ', which extends ' . RebingType::class . ': a class is either a hand-written Rebing type or an #[Input], not both. Remove one.',
        ],
        '#[Input] on an Eloquent model' => [
            [Invalid\InputModel::class],
            '#[Input] on the Eloquent model ' . Invalid\InputModel::class . ' is not supported: in input position a model is always bound by its ID. Type a parameter or an input property as InputModel to bind one, or declare a separate #[Input] class with the values to fill it with.',
        ],
        'two inputs with one name' => [
            [Inputs\Chapter::class, Invalid\DuplicateInputName::class],
            'GraphQL type name [ChapterInput] is used by both ' . Inputs\Chapter::class . ' and ' . Invalid\DuplicateInputName::class . '. Rename one with #[Input(name: ...)].',
        ],
    ]);

    it('rejects them when applied', function (array $classes, string $message) {
        isolateGraphQL();

        expect(fn() => discoverGraphQL(...$classes)->apply())->toThrow(LogicException::class, $message);
    })->with([
        'an input returned from an action' => [
            [Invalid\InputReturnQuery::class, Inputs\Chapter::class],
            'Method ' . Invalid\InputReturnQuery::class . '::chapter references ' . Inputs\Chapter::class . ', which is an #[Input] and has no output type. Add #[Type] to Chapter to return it too, or name a registered GraphQL type with type: (or of: for a list) on #[Query].',
        ],
        'an input as a field of a #[Type]' => [
            [Invalid\InputFieldOnType::class, Inputs\Chapter::class],
            'Field InputFieldOn.chapter references ' . Inputs\Chapter::class . ', which is an #[Input] and has no output type. Add #[Type] to Chapter to return it too, or name a registered GraphQL type with type: (or of: for a list) on #[Field].',
        ],
        'a used input with an unregistered class property' => [
            [Invalid\UnregisteredPropertyMutation::class, Invalid\UnregisteredProperty::class],
            'Field UnregisteredPropertyInput.at references DateTimeImmutable, which is not a registered GraphQL input type. Use a scalar, an enum or an #[Input] class, or name a registered GraphQL input type with #[Field(type: ...)].',
        ],
    ]);

    it('rejects a #[Type] field arg that takes a discovered input by name', function (string $fixture, string $arg, string $field) {
        refreshMappers(Inputs\DraftsMapper::class);

        isolateGraphQL();

        expect(fn() => discoverGraphQL(...[...CREATE_BOOK_SOURCES, $fixture])->apply())->toThrow(LogicException::class, sprintf(
            'Argument %s of field %s.%s takes the input type [CreateBookInput], which fields do not support yet: field args are neither validated, hydrated nor authorized. Take scalar args instead, or move the operation to a #[Query] or #[Mutation].',
            $arg,
            class_basename($fixture),
            $field,
        ));
    })->with([
        '#[Arg(type:)] on an array' => [Invalid\RawInputFieldArg::class, 'raw', 'publisher'],
        'a mapped parameter' => [Invalid\MappedInputFieldArg::class, 'drafts', 'count'],
    ]);

    it('does not check the fields of an input that nothing uses', function () {
        isolateGraphQL();

        discoverGraphQL(Inputs\ClockQuery::class, Invalid\UnregisteredProperty::class)->apply();

        expect(config('graphql.types'))->toBe([]);
    });
});

describe('type mappers in input position', function () {
    it('maps an input property through the scalar map and hydrates the parsed value', function () {
        config()->set(ScalarMap::CONFIG, [CarbonInterface::class => 'DateTime']);
        refreshMappers();

        $sdl = schemaSdlWith(['DateTime' => Mappers\DateTimeScalar::class], Inputs\EventMutation::class, Inputs\Event::class);

        expect($sdl)->toContain(<<<'GRAPHQL'
            input EventInput {
              startsAt: DateTime!
              endsAt: DateTime
            }
            GRAPHQL);

        buildAllSchemas();

        $this->postJson('/graphql', ['query' => 'mutation { schedule(event: { startsAt: "2026-05-01T09:00:00+00:00" }) }'])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertExactJson(['data' => ['schedule' => '2026-05-01']]);

        expect(Inputs\EventMutation::$received?->startsAt)->toBeInstanceOf(CarbonImmutable::class)
            ->and(Inputs\EventMutation::$received?->endsAt)->toBeNull();
    });

    it('asks the mappers about input properties as properties in input position', function () {
        refreshMappers(Inputs\MemberRecordingMapper::class);
        Inputs\MemberRecordingMapper::$asked = [];

        discoverGraphQL(Inputs\Chapter::class);

        expect(array_map(static fn(Member $member): array => [$member->name, $member->declaringClass, $member->position, $member->kind], Inputs\MemberRecordingMapper::$asked))
            ->toContain(['title', Inputs\Chapter::class, Position::Input, MemberKind::Property]);
    });

    it('lets a mapper type a property whose class has no input form', function () {
        refreshMappers(greedyMapper());

        [$input] = discoveredTypesOf(TypeKind::Input, Invalid\OutputTypeProperty::class);

        expect($input->fields[0]->type)->toEqual(TypeRef::scalar('String'));
    });

    it('never offers an #[Input] parameter or property to a mapper', function () {
        refreshMappers(greedyMapper());

        $arg = discoveredActions(Inputs\BookMutations::class)['createBook']->args[0];
        $fields = [];

        foreach (discoveredTypesOf(TypeKind::Input, Inputs\CreateBook::class) as $type) {
            foreach ($type->fields as $field) {
                $fields[$field->name] = $field->type;
            }
        }

        expect([$arg->input, $arg->type, $arg->typeRef])->toBe([true, Inputs\CreateBook::class, null])
            ->and($fields['shipTo'])->toEqual(TypeRef::class(Inputs\Address::class, nullable: true))
            ->and($fields['chapters'])->toEqual(TypeRef::class(Inputs\Chapter::class, list: true, nullable: true))
            ->and($fields['title'])->toEqual(TypeRef::scalar('String'))
            ->and($fields['publisher'])->toEqual(TypeRef::scalar('ID'));
    });
});

describe('framework members', function () {
    it('leaves properties Laravel declares out of an input', function () {
        [$input] = discoveredTypesOf(TypeKind::Input, Inputs\QueuedReport::class);

        expect(array_map(static fn($field) => $field->name, $input->fields))->toBe(['title']);
    });
});

describe('inputs referenced through #[Arg]', function () {
    it('emits an input named by #[Arg(type:)] and passes the raw value', function () {
        expect(schemaSdl(Inputs\ChapterArgQuery::class, Inputs\Chapter::class))
            ->toContain('input ChapterInput {')
            ->toContain('rawChapter(chapter: ChapterInput!): String!');

        $this->postJson('/graphql', ['query' => '{ rawChapter(chapter: { title: "One" }) }'])
            ->assertOk()
            ->assertExactJson(['data' => ['rawChapter' => 'One']]);
    });

    it('applies #[Arg(rules:)] on an input parameter at the arg path', function () {
        schemaSdl(Inputs\ChapterArgQuery::class, Inputs\Chapter::class);

        $response = $this->postJson('/graphql', ['query' => '{ pairedChapter(chapter: { title: "One" }) }'])->assertOk();

        expect($response->json('errors.0.extensions.validation'))->toBe([
            'chapter' => ['The chapter field must contain 2 items.'],
        ]);
    });
});
