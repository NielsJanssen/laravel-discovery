<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Gate;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\InputHydrator;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\InputCollector;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\ScalarMap;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Omitted;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Position;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use Tempest\Reflection\ClassReflector;
use Tests\Fixtures\RebingGraphQL\Inputs\Omitted as Partial;
use Tests\Fixtures\RebingGraphQL\Inputs\Omitted\Invalid;
use Tests\Fixtures\RebingGraphQL\Mappers;
use Workbench\App\Models\User;

/** The partial-update fixtures, in discovery order. */
const PATCH_SOURCES = [
    Partial\BookPatches::class,
    Partial\UpdateBook::class,
    Partial\RetagBook::class,
    Partial\Note::class,
    Partial\Tone::class,
    Partial\AssignBook::class,
    Partial\AssignBatch::class,
    Partial\RenameBook::class,
];

/**
 * @param  array<string, mixed>  $variables
 * @return array{query: string, variables: array<string, mixed>}
 */
function patchRequest(string $field, string $input, array $variables): array
{
    return [
        'query' => "mutation (\$input: $input!) { $field(input: \$input) }",
        'variables' => ['input' => $variables],
    ];
}

beforeEach(function () {
    Partial\BookPatches::$received = null;
    Partial\RescheduleMutation::$received = null;
});

describe('the schema', function () {
    it('prints an Omitted property as its inner type, optional and without a default', function () {
        $definitions = sdlDefinitions(schemaSdl(...PATCH_SOURCES));

        expect($definitions)->toContain(
            <<<'GRAPHQL'
                input UpdateBookInput {
                  title: String
                  subtitle: String
                }
                GRAPHQL,
            <<<'GRAPHQL'
                input RetagBookInput {
                  tone: Tone
                  note: NoteInput
                  notes: [NoteInput!]
                  pages: Int
                }
                GRAPHQL,
            <<<'GRAPHQL'
                input NoteInput {
                  text: String!
                  tag: String
                }
                GRAPHQL,
            <<<'GRAPHQL'
                input AssignBookInput {
                  editor: ID
                  reviewer: ID
                  owner: ID
                }
                GRAPHQL,
            <<<'GRAPHQL'
                enum Tone {
                  Light
                  Dark
                }
                GRAPHQL,
            <<<'GRAPHQL'
                type Mutation {
                  updateBook(input: UpdateBookInput!): String!
                  patchBook(title: String, subtitle: String): String!
                  retagBook(input: RetagBookInput!): String!
                  assignBook(input: AssignBookInput!): String!
                  assignFlat(editor: ID, reviewer: ID, owner: ID): String!
                  assignBatch(input: AssignBatchInput!): String!
                  renameBook(input: RenameBookInput!): String!
                  renameFlat(plain: String, sub_title: String): String!
                }
                GRAPHQL,
        );
    });
});

describe('partial updates', function () {
    it('hydrates an absent field to Omitted, an explicit null to null and a value to itself', function (array $input, string|Omitted $title, string|Omitted|null $subtitle) {
        schemaSdl(...PATCH_SOURCES);

        $this->postJson('/graphql', patchRequest('updateBook', 'UpdateBookInput', $input))
            ->assertOk()
            ->assertExactJson(['data' => ['updateBook' => 'ok']]);

        expect(Partial\BookPatches::$received)->toEqual(new Partial\UpdateBook($title, $subtitle));
    })->with([
        'both absent' => [[], Omitted::Value, Omitted::Value],
        'subtitle null' => [['subtitle' => null], Omitted::Value, null],
        'both given' => [['title' => 'Dune', 'subtitle' => 'Messiah'], 'Dune', 'Messiah'],
        'title given, subtitle absent' => [['title' => 'Dune'], 'Dune', Omitted::Value],
    ]);

    it('tells absent from null in inline literals too', function () {
        schemaSdl(...PATCH_SOURCES);

        $this->postJson('/graphql', ['query' => 'mutation { updateBook(input: {}) }'])->assertOk()->assertJsonMissingPath('errors');

        expect(Partial\BookPatches::$received)->toEqual(new Partial\UpdateBook());

        $this->postJson('/graphql', ['query' => 'mutation { updateBook(input: { subtitle: null }) }'])->assertOk()->assertJsonMissingPath('errors');

        expect(Partial\BookPatches::$received)->toEqual(new Partial\UpdateBook(subtitle: null));
    });

    it('rejects an explicit null when the PHP type takes none', function () {
        schemaSdl(...PATCH_SOURCES);

        $response = $this->postJson('/graphql', patchRequest('updateBook', 'UpdateBookInput', ['title' => null]))
            ->assertOk()
            ->assertJsonPath('errors.0.message', 'validation');

        expect($response->json('errors.0.extensions.validation'))->toBe([
            'input.title' => ['The input.title field may be left out, but not set to null.'],
        ])->and(Partial\BookPatches::$received)->toBeNull();
    });

    it('runs the rules of a given field and skips those of an absent one', function () {
        schemaSdl(...PATCH_SOURCES);

        $response = $this->postJson('/graphql', patchRequest('updateBook', 'UpdateBookInput', ['title' => 'D']))->assertOk();

        expect($response->json('errors.0.extensions.validation'))->toBe([
            'input.title' => ['A title needs two letters.'],
        ]);

        $this->postJson('/graphql', patchRequest('updateBook', 'UpdateBookInput', ['subtitle' => 'x']))
            ->assertOk()
            ->assertJsonMissingPath('errors');
    });
});

describe('nested inputs, lists and enums', function () {
    it('leaves absent nested, list, enum and ruled fields Omitted, without running an implicit rule', function () {
        schemaSdl(...PATCH_SOURCES);

        $this->postJson('/graphql', patchRequest('retagBook', 'RetagBookInput', []))
            ->assertOk()
            ->assertJsonMissingPath('errors');

        expect(Partial\BookPatches::$received)->toEqual(new Partial\RetagBook());
    });

    it('hydrates given values into enums, nested inputs and list elements, each with its own Omitted fields', function () {
        schemaSdl(...PATCH_SOURCES);

        $this->postJson('/graphql', patchRequest('retagBook', 'RetagBookInput', [
            'tone' => 'Dark',
            'note' => ['text' => 'first'],
            'notes' => [['text' => 'a'], ['text' => 'b', 'tag' => 'x']],
            'pages' => 12,
        ]))->assertOk()->assertJsonMissingPath('errors');

        expect(Partial\BookPatches::$received)->toEqual(new Partial\RetagBook(
            tone: Partial\Tone::Dark,
            note: new Partial\Note('first'),
            notes: [new Partial\Note('a'), new Partial\Note('b', 'x')],
            pages: 12,
        ));
    });

    it('takes an explicit null for a nested input whose PHP type allows it', function () {
        schemaSdl(...PATCH_SOURCES);

        $this->postJson('/graphql', patchRequest('retagBook', 'RetagBookInput', ['note' => null]))
            ->assertOk()
            ->assertJsonMissingPath('errors');

        expect(Partial\BookPatches::$received)->toEqual(new Partial\RetagBook(note: null));
    });

    it('rejects a null for a list, an enum, a ruled field and a field inside a list element', function () {
        schemaSdl(...PATCH_SOURCES);

        $response = $this->postJson('/graphql', patchRequest('retagBook', 'RetagBookInput', [
            'tone' => null,
            'notes' => [['text' => 'a'], ['text' => 'b', 'tag' => null]],
            'pages' => null,
        ]))->assertOk();

        expect($response->json('errors.0.extensions.validation'))->toEqualCanonicalizing([
            'input.tone' => ['The input.tone field may be left out, but not set to null.'],
            'input.pages' => ['The input.pages field may be left out, but not set to null.'],
            'input.notes.1.tag' => ['The input.notes.1.tag field may be left out, but not set to null.'],
        ])->and(Partial\BookPatches::$received)->toBeNull();

        $response = $this->postJson('/graphql', patchRequest('retagBook', 'RetagBookInput', ['notes' => null]))->assertOk();

        expect($response->json('errors.0.extensions.validation'))->toBe([
            'input.notes' => ['The input.notes field may be left out, but not set to null.'],
        ]);
    });

    it('runs the declared rules of a given field', function () {
        schemaSdl(...PATCH_SOURCES);

        $response = $this->postJson('/graphql', patchRequest('retagBook', 'RetagBookInput', ['pages' => 0]))->assertOk();

        expect($response->json('errors.0.extensions.validation'))->toBe([
            'input.pages' => ['The input.pages field must be at least 1.'],
        ]);
    });
});

describe('#[AsArgs]', function () {
    it('hydrates the flattened fields from absent, null and given args', function () {
        schemaSdl(...PATCH_SOURCES);

        $this->postJson('/graphql', ['query' => 'mutation { patchBook }'])->assertOk()->assertJsonMissingPath('errors');

        expect(Partial\BookPatches::$received)->toEqual(new Partial\UpdateBook());

        $this->postJson('/graphql', ['query' => 'mutation { patchBook(subtitle: null) }'])->assertOk()->assertJsonMissingPath('errors');

        expect(Partial\BookPatches::$received)->toEqual(new Partial\UpdateBook(subtitle: null));

        $this->postJson('/graphql', ['query' => 'mutation { patchBook(title: "Dune") }'])->assertOk()->assertJsonMissingPath('errors');

        expect(Partial\BookPatches::$received)->toEqual(new Partial\UpdateBook(title: 'Dune'));
    });

    it('rejects a null, and runs the rules of a given flattened field only', function () {
        schemaSdl(...PATCH_SOURCES);

        $response = $this->postJson('/graphql', ['query' => 'mutation { patchBook(title: null) }'])->assertOk();

        expect($response->json('errors.0.extensions.validation'))->toBe([
            'title' => ['The title field may be left out, but not set to null.'],
        ]);

        $response = $this->postJson('/graphql', ['query' => 'mutation { patchBook(title: "D") }'])->assertOk();

        expect($response->json('errors.0.extensions.validation'))->toBe([
            'title' => ['A title needs two letters.'],
        ])->and(Partial\BookPatches::$received)->toBeNull();
    });
});

describe('model-bound properties', function () {
    beforeEach(function () {
        $this->loadLaravelMigrations();
        Gate::define('edit', fn(?User $actor, User $subject) => $subject->name !== 'Denied');
        Gate::define('review', fn(?User $actor, User $subject) => $subject->name !== 'Denied');
    });

    dataset('assign through', [
        'an input arg' => [
            static fn(array $values): array => patchRequest('assignBook', 'AssignBookInput', $values),
            'input.',
        ],
        'flattened args' => [
            static fn(array $values): array => [
                'query' => 'mutation ($editor: ID, $reviewer: ID, $owner: ID) { assignFlat(editor: $editor, reviewer: $reviewer, owner: $owner) }',
                'variables' => $values,
            ],
            '',
        ],
    ]);

    it('skips the exists rule and the #[Authorize] check of an absent model', function (callable $request) {
        Gate::define('edit', fn(?User $actor) => false);
        Gate::define('review', fn(?User $actor) => false);
        schemaSdl(...PATCH_SOURCES);

        $this->postJson('/graphql', $request([]))
            ->assertOk()
            ->assertJsonMissingPath('errors');

        expect(Partial\BookPatches::$received)->toEqual(new Partial\AssignBook());
    })->with('assign through');

    it('binds and authorizes a given model', function (callable $request) {
        $editor = User::factory()->create(['name' => 'Ada']);
        $reviewer = User::factory()->create(['name' => 'Grace']);
        $owner = User::factory()->create(['name' => 'Alan']);
        schemaSdl(...PATCH_SOURCES);

        $this->postJson('/graphql', $request(['editor' => $editor->id, 'reviewer' => $reviewer->id, 'owner' => $owner->id]))
            ->assertOk()
            ->assertJsonMissingPath('errors');

        $received = Partial\BookPatches::$received;

        expect($received)->toBeInstanceOf(Partial\AssignBook::class)
            ->and($received->editor instanceof User && $received->editor->is($editor))->toBeTrue()
            ->and($received->reviewer instanceof User && $received->reviewer->is($reviewer))->toBeTrue()
            ->and($received->owner instanceof User && $received->owner->is($owner))->toBeTrue();
    })->with('assign through');

    it('denies a given model its ability rejects, before validation', function (callable $request) {
        $denied = User::factory()->create(['name' => 'Denied']);
        schemaSdl(...PATCH_SOURCES);

        $this->postJson('/graphql', $request(['editor' => $denied->id, 'owner' => 999999]))
            ->assertOk()
            ->assertJsonPath('errors.0.message', 'Forbidden')
            ->assertJsonPath('errors.0.extensions.category', 'authorization');

        $this->postJson('/graphql', $request(['reviewer' => $denied->id]))
            ->assertOk()
            ->assertJsonPath('errors.0.message', 'Forbidden');

        expect(Partial\BookPatches::$received)->toBeNull();
    })->with('assign through');

    it('denies a given id with no record when the PHP type takes no null', function (callable $request) {
        schemaSdl(...PATCH_SOURCES);

        $this->postJson('/graphql', $request(['editor' => 999999]))
            ->assertOk()
            ->assertJsonPath('errors.0.message', 'Forbidden');

        expect(Partial\BookPatches::$received)->toBeNull();
    })->with('assign through');

    it('runs the exists rule for a given model without #[Authorize]', function (callable $request, string $prefix) {
        schemaSdl(...PATCH_SOURCES);

        $response = $this->postJson('/graphql', $request(['owner' => 999999]))->assertOk();

        expect($response->json('errors.0.extensions.validation'))->toBe([
            "{$prefix}owner" => ["The selected {$prefix}owner is invalid."],
        ])->and(Partial\BookPatches::$received)->toBeNull();
    })->with('assign through');

    it('reports an explicit null as a validation error, not as Forbidden', function (callable $request, string $prefix) {
        schemaSdl(...PATCH_SOURCES);

        $response = $this->postJson('/graphql', $request(['editor' => null, 'owner' => null]))->assertOk();

        expect($response->json('errors.0.extensions.validation'))->toBe([
            "{$prefix}editor" => ["The {$prefix}editor field may be left out, but not set to null."],
            "{$prefix}owner" => ["The {$prefix}owner field may be left out, but not set to null."],
        ])->and(Partial\BookPatches::$received)->toBeNull();
    })->with('assign through');

    it('binds null, or a missing record as null, when the PHP type takes null', function (callable $request) {
        schemaSdl(...PATCH_SOURCES);

        $this->postJson('/graphql', $request(['reviewer' => null]))->assertOk()->assertJsonMissingPath('errors');

        expect(Partial\BookPatches::$received)->toEqual(new Partial\AssignBook(reviewer: null));

        $this->postJson('/graphql', $request(['reviewer' => 999999]))->assertOk()->assertJsonMissingPath('errors');

        expect(Partial\BookPatches::$received)->toEqual(new Partial\AssignBook(reviewer: null));
    })->with('assign through');
});

describe('a renamed field and a non-promoted property', function () {
    dataset('rename through', [
        'an input arg' => [
            static fn(array $values): array => patchRequest('renameBook', 'RenameBookInput', $values),
            'input.',
        ],
        'flattened args' => [
            static fn(array $values): array => [
                'query' => 'mutation ($plain: String, $sub_title: String) { renameFlat(plain: $plain, sub_title: $sub_title) }',
                'variables' => $values,
            ],
            '',
        ],
    ]);

    it('prints the renamed field and the plain property as optional fields', function () {
        expect(sdlDefinitions(schemaSdl(...PATCH_SOURCES)))->toContain(<<<'GRAPHQL'
            input RenameBookInput {
              plain: String
              sub_title: String
            }
            GRAPHQL);
    });

    it('leaves both Omitted when absent', function (callable $request) {
        schemaSdl(...PATCH_SOURCES);

        $this->postJson('/graphql', $request([]))->assertOk()->assertJsonMissingPath('errors');

        expect(Partial\BookPatches::$received)->toEqual(new Partial\RenameBook());
    })->with('rename through');

    it('hydrates given values into the renamed and the plain property', function (callable $request) {
        schemaSdl(...PATCH_SOURCES);

        $this->postJson('/graphql', $request(['sub_title' => 'Messiah', 'plain' => 'kept']))->assertOk()->assertJsonMissingPath('errors');

        $expected = new Partial\RenameBook('Messiah');
        $expected->plain = 'kept';

        expect(Partial\BookPatches::$received)->toEqual($expected);
    })->with('rename through');

    it('rejects a null at the renamed path', function (callable $request, string $prefix) {
        schemaSdl(...PATCH_SOURCES);

        $response = $this->postJson('/graphql', $request(['sub_title' => null, 'plain' => null]))->assertOk();

        expect($response->json('errors.0.extensions.validation'))->toEqualCanonicalizing([
            "{$prefix}sub_title" => ["The {$prefix}sub title field may be left out, but not set to null."],
            "{$prefix}plain" => ["The {$prefix}plain field may be left out, but not set to null."],
        ])->and(Partial\BookPatches::$received)->toBeNull();
    })->with('rename through');

    it('runs the rules of the renamed field only when given', function (callable $request, string $prefix) {
        schemaSdl(...PATCH_SOURCES);

        $response = $this->postJson('/graphql', $request(['sub_title' => 'Me']))->assertOk();

        expect($response->json('errors.0.extensions.validation'))->toBe([
            "{$prefix}sub_title" => ['A subtitle needs three letters.'],
        ])->and(Partial\BookPatches::$received)->toBeNull();

        $this->postJson('/graphql', $request(['plain' => 'x']))->assertOk()->assertJsonMissingPath('errors');
    })->with('rename through');
});

describe('model properties in nested inputs and list items', function () {
    beforeEach(function () {
        $this->loadLaravelMigrations();
    });

    it('binds nothing and checks nothing for an absent model, even with a gate that denies everything', function () {
        Gate::define('edit', fn(?User $actor) => false);
        Gate::define('review', fn(?User $actor) => false);
        schemaSdl(...PATCH_SOURCES);

        $this->postJson('/graphql', patchRequest('assignBatch', 'AssignBatchInput', ['items' => [[], []], 'single' => []]))
            ->assertOk()
            ->assertJsonMissingPath('errors');

        expect(Partial\BookPatches::$received)->toEqual(new Partial\AssignBatch(
            items: [new Partial\AssignBook(), new Partial\AssignBook()],
            single: new Partial\AssignBook(),
        ));
    });

    it('denies a given model in a list item', function () {
        Gate::define('edit', fn(?User $actor, User $subject) => $subject->name !== 'Denied');
        $allowed = User::factory()->create(['name' => 'Ada']);
        $denied = User::factory()->create(['name' => 'Denied']);
        schemaSdl(...PATCH_SOURCES);

        $this->postJson('/graphql', patchRequest('assignBatch', 'AssignBatchInput', ['items' => [['editor' => $allowed->id], ['editor' => $denied->id]]]))
            ->assertOk()
            ->assertJsonPath('errors.0.message', 'Forbidden')
            ->assertJsonPath('errors.0.extensions.category', 'authorization');

        expect(Partial\BookPatches::$received)->toBeNull();

        $this->postJson('/graphql', patchRequest('assignBatch', 'AssignBatchInput', ['single' => ['editor' => $denied->id]]))
            ->assertOk()
            ->assertJsonPath('errors.0.message', 'Forbidden');

        $this->postJson('/graphql', patchRequest('assignBatch', 'AssignBatchInput', ['items' => [['editor' => $allowed->id]], 'single' => ['editor' => $allowed->id]]))
            ->assertOk()
            ->assertJsonMissingPath('errors');

        $received = Partial\BookPatches::$received;

        expect($received)->toBeInstanceOf(Partial\AssignBatch::class)
            ->and($received->items[0]->editor instanceof User && $received->items[0]->editor->is($allowed))->toBeTrue()
            ->and($received->single?->editor instanceof User && $received->single->editor->is($allowed))->toBeTrue();
    });

    it('reports an explicit null in a list item at its full path', function () {
        Gate::define('edit', fn(?User $actor) => false);
        schemaSdl(...PATCH_SOURCES);

        $response = $this->postJson('/graphql', patchRequest('assignBatch', 'AssignBatchInput', ['items' => [['editor' => null]], 'single' => ['owner' => null]]))
            ->assertOk();

        expect($response->json('errors.0.extensions.validation'))->toEqualCanonicalizing([
            'input.items.0.editor' => ['The input.items.0.editor field may be left out, but not set to null.'],
            'input.single.owner' => ['The input.single.owner field may be left out, but not set to null.'],
        ])->and(Partial\BookPatches::$received)->toBeNull();
    });
});

describe('mapped types', function () {
    it('maps the inner type of an Omitted property and hydrates the parsed value', function () {
        config()->set(ScalarMap::CONFIG, [CarbonInterface::class => 'DateTime']);
        refreshMappers();

        $sdl = schemaSdlWith(['DateTime' => Mappers\DateTimeScalar::class], Partial\RescheduleMutation::class, Partial\Reschedule::class);

        expect($sdl)->toContain(<<<'GRAPHQL'
            input RescheduleInput {
              startsAt: DateTime
            }
            GRAPHQL);

        $this->postJson('/graphql', ['query' => 'mutation { reschedule(input: {}) }'])->assertOk()->assertJsonMissingPath('errors');

        expect(Partial\RescheduleMutation::$received)->toEqual(new Partial\Reschedule());

        $this->postJson('/graphql', ['query' => 'mutation { reschedule(input: { startsAt: "2026-05-01T09:00:00+00:00" }) }'])
            ->assertOk()
            ->assertJsonMissingPath('errors');

        expect(Partial\RescheduleMutation::$received?->startsAt)->toBeInstanceOf(CarbonImmutable::class);
    });
});

describe('hydration without a request', function () {
    it('keeps Omitted for absent values and drops a null the PHP type does not take', function () {
        $hydrator = new InputHydrator(app());

        expect($hydrator->hydrate(Partial\RetagBook::class, ['tone' => 'Light', 'pages' => null]))
            ->toEqual(new Partial\RetagBook(tone: Partial\Tone::Light));
    });

    it('keeps each hydration of the same class to its own values', function () {
        $hydrator = new InputHydrator(app());

        expect($hydrator->hydrate(Partial\RetagBook::class, ['pages' => 3, 'note' => null]))
            ->toEqual(new Partial\RetagBook(note: null, pages: 3))
            ->and($hydrator->hydrate(Partial\RetagBook::class, ['tone' => 'Dark']))
            ->toEqual(new Partial\RetagBook(tone: Partial\Tone::Dark));
    });
});

describe('rejected shapes', function () {
    it('rejects them at discovery', function (object $shape, string $format) {
        expectRejected($shape, $format);
    })->with([
        'Omitted next to more than one type' => [
            fn() => new #[Input] readonly class {
                public function __construct(
                    public string|int|Omitted $value = Omitted::Value,
                ) {}
            },
            'Property %1$s::$value is typed ' . Omitted::class . '|string|int, but Omitted makes exactly one type optional, as in string|Omitted or string|Omitted|null. Keep one type besides Omitted and null.',
        ],
        'Omitted on its own' => [
            fn() => new #[Input] readonly class {
                public function __construct(
                    public ?Omitted $value = Omitted::Value,
                ) {}
            },
            'Property %1$s::$value is typed ?' . Omitted::class . ', which leaves no value to send besides Omitted. Name the type Omitted makes optional, as in string|Omitted.',
        ],
        'an Omitted property without a default' => [
            fn() => new #[Input] readonly class ('x') {
                public function __construct(
                    public string|Omitted $title,
                ) {}
            },
            'Property %1$s::$title is typed ' . Omitted::class . '|string, but it has no default, so a field the caller leaves out has nothing to hydrate to. Give it the default Omitted::Value.',
        ],
        'an Omitted property with another default' => [
            fn() => new #[Input] readonly class {
                public function __construct(
                    public string|Omitted|null $subtitle = null,
                ) {}
            },
            'Property %1$s::$subtitle is typed ' . Omitted::class . '|string|null, but its default is not Omitted::Value, so a field the caller leaves out has nothing to hydrate to. Give it the default Omitted::Value.',
        ],
        'Omitted on an action parameter' => [
            fn() => new class {
                #[Mutation]
                public function rename(string|Omitted $title = Omitted::Value): string
                {
                    return 'ok';
                }
            },
            'Parameter $title in %1$s::rename is typed ' . Omitted::class . '|string, but Omitted only applies to a property of an #[Input] class. Move the optional args into an #[Input] class and take it with #[AsArgs] to keep them top-level.',
        ],
        'Omitted in an inferred return type' => [
            fn() => new class {
                #[Query]
                public function title(): string|Omitted
                {
                    return Omitted::Value;
                }
            },
            'Method %1$s::title is typed ' . Omitted::class . '|string, but Omitted only applies to a property of an #[Input] class, in input position. Remove Omitted from the return type.',
        ],
        'Omitted in a return type with type:' => [
            fn() => new class {
                #[Query(type: 'String')]
                public function title(): string|Omitted
                {
                    return Omitted::Value;
                }
            },
            'Method %1$s::title is typed ' . Omitted::class . '|string, but Omitted only applies to a property of an #[Input] class, in input position. Remove Omitted from the return type.',
        ],
        'Omitted in a return type with of:' => [
            fn() => new class {
                /**
                 * @return list<string>|Omitted
                 */
                #[Query(of: 'string')]
                public function titles(): array|Omitted
                {
                    return Omitted::Value;
                }
            },
            'Method %1$s::titles is typed ' . Omitted::class . '|array, but Omitted only applies to a property of an #[Input] class, in input position. Remove Omitted from the return type.',
        ],
    ]);

    it('rejects Omitted on an output-only #[Type] property', function () {
        expect(fn() => discoverGraphQL(Invalid\OutputOnly::class))->toThrow(
            LogicException::class,
            sprintf('Property %s::$title is typed %s|string, but Omitted only applies to a property of an #[Input] class, in input position. Remove Omitted from the type.', Invalid\OutputOnly::class, Omitted::class),
        );
    });

    it('rejects Omitted on a class that is both #[Type] and #[Input]', function () {
        expect(fn() => discoverGraphQL(Invalid\Shared::class))->toThrow(
            LogicException::class,
            sprintf('Property %s::$title is typed %s|string, but Shared is both a #[Type] and an #[Input], and Omitted has no meaning in output position. Remove Omitted, or declare the partial update as its own #[Input] class.', Invalid\Shared::class, Omitted::class),
        );
    });

    it('rejects the same on the input side of a shared class', function () {
        $collector = app(InputCollector::class);

        expect(fn() => $collector->collect(new ClassReflector(Invalid\Shared::class), new Input()))->toThrow(
            LogicException::class,
            sprintf('Property %s::$title is typed %s|string, but Shared is both a #[Type] and an #[Input]', Invalid\Shared::class, Omitted::class),
        );
    });
});

describe('the discovery cache', function () {
    it('round-trips the omittable fields and their model bindings', function () {
        $types = array_column(discoveredTypes(...PATCH_SOURCES), null, 'class');

        foreach ([Partial\UpdateBook::class, Partial\RetagBook::class, Partial\AssignBook::class] as $class) {
            expect(unserialize(serialize($types[$class])))->toEqual($types[$class]);
        }

        $action = discoveredActions(...PATCH_SOURCES)['assignFlat'];

        expect(unserialize(serialize($action)))->toEqual($action)
            ->and($action->parameters->flattenedInputs[0]->type->fields[0]->omittable)->toBeTrue();
    });

    it('resolves, validates and authorizes from serialized items', function () {
        $this->loadLaravelMigrations();
        Gate::define('edit', fn(?User $actor, User $subject) => $subject->name !== 'Denied');
        $denied = User::factory()->create(['name' => 'Denied']);

        applyCachedGraphQL(PATCH_SOURCES);

        $this->postJson('/graphql', patchRequest('updateBook', 'UpdateBookInput', ['subtitle' => null]))->assertOk()->assertJsonMissingPath('errors');

        expect(Partial\BookPatches::$received)->toEqual(new Partial\UpdateBook(subtitle: null));

        $this->postJson('/graphql', patchRequest('updateBook', 'UpdateBookInput', ['title' => null]))
            ->assertOk()
            ->assertJsonPath('errors.0.extensions.validation', ['input.title' => ['The input.title field may be left out, but not set to null.']]);

        $this->postJson('/graphql', patchRequest('assignBook', 'AssignBookInput', ['editor' => $denied->id]))
            ->assertOk()
            ->assertJsonPath('errors.0.message', 'Forbidden');
    });

    it('registers the input types an Omitted field references when the configuration is cached', function () {
        $registry = assertBoundWhenConfigCached(PATCH_SOURCES, static fn(): bool => true);

        expect($registry->nameOf(Partial\Note::class, Position::Input))->toBe('NoteInput')
            ->and($registry->nameOf(Partial\UpdateBook::class, Position::Input))->toBe('UpdateBookInput')
            ->and($registry->has(Partial\Tone::class))->toBeTrue()
            ->and($registry->has(Omitted::class))->toBeFalse();
    });
});
