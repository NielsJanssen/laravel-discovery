<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use Illuminate\Support\Facades\Gate;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use Tests\Fixtures\RebingGraphQL\Inputs;
use Workbench\App\Models\User;

/** The #[Arg(of:)] fixtures, with the item types they reference. */
const LIST_ARG_SOURCES = [
    Inputs\ListArgMutations::class,
    Inputs\Chapter::class,
    Inputs\Profile::class,
    Inputs\Shade::class,
    Inputs\Genre::class,
    Inputs\CreateBook::class,
    Inputs\Address::class,
];

beforeEach(function () {
    Inputs\ListArgMutations::$received = null;
});

describe('the schema of a list argument', function () {
    it('prints an input, a scalar and an enum list argument with their wrapping', function () {
        $sdl = schemaSdl(...LIST_ARG_SOURCES, ...[Inputs\ClockQuery::class, Inputs\Clock::class]);

        expect($sdl)
            ->toContain('addChapters(chapters: [ChapterInput!]!): String!')
            ->toContain('saveProfiles(people: [ProfileFields]): Int!')
            ->toContain('tagChapters(tags: [String!], genres: [Genre!]): String!');
    });

    it('discovers the item type as the argument type, marking only an #[Input] for hydration', function () {
        $args = discoveredActions(Inputs\ListArgMutations::class);

        $chapters = $args['addChapters']->parameters->args[0];
        $profiles = $args['saveProfiles']->parameters->args[0];
        [$tags, $genres] = $args['tagChapters']->parameters->args;

        expect($chapters->type->class)->toBe(Inputs\Chapter::class)
            ->and($chapters->type->list)->toBeTrue()
            ->and($chapters->type->nullable)->toBeFalse()
            ->and($chapters->type->nullableItems)->toBeFalse()
            ->and($chapters->input)->toBeTrue()
            ->and($profiles->name)->toBe('people')
            ->and($profiles->type->nullable)->toBeTrue()
            ->and($profiles->type->nullableItems)->toBeTrue()
            ->and($profiles->input)->toBeTrue()
            ->and($tags->type->scalar)->toBe('String')
            ->and($tags->type->list)->toBeTrue()
            ->and($tags->input)->toBeFalse()
            ->and($genres->type->class)->toBe(Inputs\Genre::class)
            ->and($genres->input)->toBeFalse();
    });
});

describe('resolving a list argument', function () {
    it('hydrates every item of a list of inputs', function () {
        schemaSdl(...LIST_ARG_SOURCES);

        $this->postJson('/graphql', ['query' => 'mutation { addChapters(chapters: [{ title: "One" }, { title: "Two" }]) }'])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertExactJson(['data' => ['addChapters' => 'One, Two']]);

        expect(Inputs\ListArgMutations::$received)->toEqual([new Inputs\Chapter('One'), new Inputs\Chapter('Two')]);
    });

    it('hydrates renamed fields and keeps a null item null', function () {
        schemaSdl(...LIST_ARG_SOURCES);

        $this->postJson('/graphql', ['query' => 'mutation { saveProfiles(people: [{ fullName: "Ada", nickname: "ada", shouted: "hi", volume: 9 }, null]) }'])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertExactJson(['data' => ['saveProfiles' => 2]]);

        $profiles = Inputs\ListArgMutations::$received;

        expect($profiles[0])->toBeInstanceOf(Inputs\Profile::class)
            ->and($profiles[0]->name)->toBe('Ada')
            ->and($profiles[0]->volume)->toBe(9)
            ->and($profiles[1])->toBeNull();
    });

    it('passes the default through when the argument is left out', function () {
        schemaSdl(...LIST_ARG_SOURCES);

        $this->postJson('/graphql', ['query' => 'mutation { saveProfiles }'])
            ->assertOk()
            ->assertExactJson(['data' => ['saveProfiles' => 0]]);

        expect(Inputs\ListArgMutations::$received)->toBeNull();
    });

    it('reads a list of scalars and a list of enum cases without hydrating', function () {
        schemaSdl(...LIST_ARG_SOURCES);

        $this->postJson('/graphql', ['query' => 'mutation { tagChapters(tags: ["a", "b"], genres: [Fiction, NonFiction]) }'])
            ->assertOk()
            ->assertExactJson(['data' => ['tagChapters' => 'a,b,Fiction,NonFiction']]);

        expect(Inputs\ListArgMutations::$received)->toBe([
            'tags' => ['a', 'b'],
            'genres' => [Inputs\Genre::Fiction, Inputs\Genre::NonFiction],
        ]);
    });

    it('validates every item at its index', function () {
        schemaSdl(...LIST_ARG_SOURCES);

        $response = $this->postJson('/graphql', ['query' => 'mutation { addChapters(chapters: [{ title: "One" }, { title: "T" }]) }'])
            ->assertOk()
            ->assertJsonPath('errors.0.message', 'validation');

        expect(Inputs\ListArgMutations::$received)->toBeNull()
            ->and($response->json('errors.0.extensions.validation'))->toBe([
                'chapters.1.title' => ['A chapter title needs two letters.'],
            ]);
    });
});

describe('a bound model inside a list item', function () {
    beforeEach(function () {
        $this->loadLaravelMigrations();
    });

    it('binds and authorizes the record of every item', function () {
        $allowed = User::factory()->create(['name' => 'Chilton']);
        $denied = User::factory()->create(['name' => 'Gollancz']);
        Gate::define('attach', fn(?User $actor, User $publisher) => $publisher->name !== 'Gollancz');
        schemaSdl(...LIST_ARG_SOURCES);

        $books = static fn(int $publisher): array => [
            'query' => 'mutation ($books: [CreateBookInput!]!) { addBooks(books: $books) }',
            'variables' => ['books' => [
                ['title' => 'Dune', 'genre' => 'Fiction', 'publisher' => $allowed->id],
                ['title' => 'Nova', 'genre' => 'Fiction', 'publisher' => $publisher],
            ]],
        ];

        $this->postJson('/graphql', $books($allowed->id))
            ->assertOk()
            ->assertExactJson(['data' => ['addBooks' => 'Dune, Nova']]);

        expect(Inputs\ListArgMutations::$received[1]->publisher->is($allowed))->toBeTrue();

        Inputs\ListArgMutations::$received = null;

        $this->postJson('/graphql', $books($denied->id))
            ->assertOk()
            ->assertJsonPath('errors.0.message', 'Forbidden')
            ->assertJsonPath('errors.0.extensions.category', 'authorization');

        expect(Inputs\ListArgMutations::$received)->toBeNull();
    });
});

describe('rejected list arguments', function () {
    it('rejects them at discovery', function (object $shape, string $format) {
        expectRejected($shape, $format);
    })->with([
        'both type: and of:' => [
            fn() => new class {
                #[Query]
                public function search(#[Arg(type: 'ChapterInput', of: Inputs\Chapter::class)] array $chapters): string
                {
                    return (string) count($chapters);
                }
            },
            'Parameter $chapters in %1$s::search sets both type: and of: on #[Arg]',
        ],
        'a type that holds no list' => [
            fn() => new class {
                #[Query]
                public function rename(#[Arg(of: Inputs\Chapter::class)] string $title): string
                {
                    return $title;
                }
            },
            'Parameter $title in %1$s::rename has type string, which holds no list, but of: on #[Arg] makes it a list.',
        ],
        'a list of models' => [
            fn() => new class {
                #[Mutation]
                public function invite(#[Arg(of: User::class)] array $users): int
                {
                    return count($users);
                }
            },
            'Parameter $users in %1$s::invite references Workbench\App\Models\User, which is an Eloquent model, which is only bound as a single ID; a list of models is not supported.',
        ],
        'a model-bound parameter' => [
            fn() => new class {
                #[Mutation]
                public function promote(#[Arg(of: Inputs\Chapter::class)] User $user): int
                {
                    return (int) $user->id;
                }
            },
            '#[Arg(of:)] on the parameter $user in %1$s::promote is not supported: User binds a single record by ID',
        ],
        'an #[Input] parameter' => [
            fn() => new class {
                #[Mutation]
                public function write(#[Arg(of: Inputs\Chapter::class)] Inputs\CreateBook $book): string
                {
                    return $book->title;
                }
            },
            '#[Arg(of:)] on the parameter $book in %1$s::write is not supported: its type is the input type of the #[Input] CreateBook. Remove of:.',
        ],
    ]);
});
