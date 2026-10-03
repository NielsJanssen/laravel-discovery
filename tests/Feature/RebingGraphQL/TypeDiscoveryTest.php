<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\SchemaPrinter;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredAction;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Ignore;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Paginated;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Pagination;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Position;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeKind;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRegistry;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Tests\Fixtures\RebingGraphQL\Types\Fields\AbstractShape;
use Tests\Fixtures\RebingGraphQL\Types\Fields\FieldSourcesQuery;
use Tests\Fixtures\RebingGraphQL\Types\Fields\FieldSourcesType;
use Tests\Fixtures\RebingGraphQL\Types\Fields\NamedType;
use Tests\Fixtures\RebingGraphQL\Types\Fields\PlainEnum;
use Tests\Fixtures\RebingGraphQL\Types\Fields\Row;
use Tests\Fixtures\RebingGraphQL\Types\Fields\RowsQuery;
use Tests\Fixtures\RebingGraphQL\Types\Invalid;
use Tests\Fixtures\RebingGraphQL\Types\PamphletQuery;
use Tests\Fixtures\RebingGraphQL\Types\PamphletType;
use Tests\Fixtures\RebingGraphQL\Types\Reference\AuthorSummary;
use Tests\Fixtures\RebingGraphQL\Types\Reference\Book;
use Tests\Fixtures\RebingGraphQL\Types\Reference\BookQuery;
use Tests\Fixtures\RebingGraphQL\Types\Reference\Genre;
use Workbench\App\Models\User;

const REFERENCE_SDL = <<<'GRAPHQL'
    "A published book"
    type Book {
      id: ID!
      title: String!
      subtitle: String
      genre: Genre!
      author: AuthorSummary!
      tags: [String!]!

      "ISBN-13"
      isbn: String @deprecated(reason: "Use identifiers")

      slug: String!

      "The title, shortened"
      excerpt(length: Int = 80): String!

      related(limit: Int = 5): [Book!]!
    }

    type AuthorSummary {
      name: String!
    }

    enum Genre {
      Fiction
      Poetry
    }

    type Query {
      book: Book!
    }

    GRAPHQL;

describe('the reference Book type', function () {
    it('prints exactly the reference SDL', function () {
        expect(schemaSdl(BookQuery::class, Book::class, AuthorSummary::class, Genre::class))
            ->toBe(REFERENCE_SDL);

        buildAllSchemas();
    });

    it('prints the same definitions whatever order the classes are discovered in', function (array $order) {
        expect(sdlDefinitions(schemaSdl(...$order)))->toBe(sdlDefinitions(REFERENCE_SDL));
    })->with([
        'reference before the type' => [[AuthorSummary::class, Genre::class, Book::class, BookQuery::class]],
        'enum only through its reference' => [[BookQuery::class, Book::class, AuthorSummary::class]],
    ]);

    it('resolves a query that returns instances end to end', function () {
        schemaSdl(BookQuery::class, Book::class, AuthorSummary::class, Genre::class);

        $this->postJson('/graphql', ['query' => <<<'GRAPHQL'
            {
              book {
                id title subtitle genre author { name } tags isbn slug
                excerpt(length: 8)
                short: excerpt
                related(limit: 1) { title related { id } }
              }
            }
            GRAPHQL])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertExactJson(['data' => ['book' => [
                'id' => '1',
                'title' => 'The Left Hand of Darkness',
                'subtitle' => null,
                'genre' => 'Fiction',
                'author' => ['name' => 'Ursula K. Le Guin'],
                'tags' => ['classic', 'science fiction'],
                'isbn' => '9780441478125',
                'slug' => 'the-left-hand-of-darkness',
                'excerpt' => 'The Left...',
                'short' => 'The Left Hand of Darkness',
                'related' => [[
                    'title' => 'The Left Hand of Darkness: The Sequel',
                    'related' => [['id' => '2'], ['id' => '3']],
                ]],
            ]]]);
    });

    it('collects a type that survives serialization', function () {
        [$type] = discoveredTypes(Book::class);

        expect(unserialize(serialize($type)))->toEqual($type)
            ->and(array_column(array_map(static fn($field) => (array) $field, $type->fields), 'phpName'))
            ->toBe(['id', 'title', 'subtitle', 'genre', 'author', 'tags', 'isbn', 'slug', 'excerpt', 'related']);
    });

    it('registers the discovered types as object types', function () {
        schemaSdl(BookQuery::class, Book::class, AuthorSummary::class, Genre::class);

        $registry = app(TypeRegistry::class);

        expect($registry->nameOf(Book::class, Position::Output))->toBe('Book')
            ->and($registry->kindOf(AuthorSummary::class, Position::Output))->toBe(TypeKind::Object)
            ->and(config('graphql.types'))->toHaveKeys(['Book', 'AuthorSummary', 'Genre']);
    });
});

describe('field sources', function () {
    it('prints every supported member', function () {
        expect(schemaSdl(FieldSourcesQuery::class, FieldSourcesType::class))->toContain(<<<'GRAPHQL'
            type FieldSources {
              plain: String!
              ratio: Float!
              flag: Boolean!
              asymmetric: String!
              shout: String!
              backed: String!

              "Renamed from $count"
              renamed: Int!

              widened: String
              stillNullable: String
              sparse: [String]!
              numbers: [Int!]!
              loose: Int!
              parent: FieldSources

              "Repeats the prefix"
              withArgs(
                "Put in front"
                prefix: String!

                times: Int
              ): String!

              withService: String!
              withInjections: String!
              oldName: String! @deprecated(reason: "Use plain (since 1.2)")
              explicitDeprecation: String! @deprecated(reason: "Explicit reason")
              me: FieldSources!
              key: ID!
            }
            GRAPHQL);

        buildAllSchemas();
    });

    it('resolves every member end to end', function () {
        schemaSdl(FieldSourcesQuery::class, FieldSourcesType::class);

        $this->postJson('/graphql', ['query' => <<<'GRAPHQL'
            {
              sources {
                plain ratio flag asymmetric shout backed renamed widened stillNullable sparse numbers loose
                parent { plain }
                withArgs(prefix: "-", times: 2)
                once: withArgs(prefix: "-")
                withService withInjections oldName explicitDeprecation
                me { plain }
                key
              }
            }
            GRAPHQL])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertExactJson(['data' => ['sources' => [
                'plain' => 'plain',
                'ratio' => 0.5,
                'flag' => true,
                'asymmetric' => 'asymmetric',
                'shout' => 'PLAIN',
                'backed' => 'backed!',
                'renamed' => 3,
                'widened' => 'widened',
                'stillNullable' => 'stillNullable',
                'sparse' => ['a', null],
                'numbers' => [1, 2, 3],
                'loose' => 7,
                'parent' => null,
                'withArgs' => '--plain',
                'once' => '-plain',
                'withService' => 'plain!',
                'withInjections' => 'withInjections:same',
                'oldName' => 'plain',
                'explicitDeprecation' => 'plain',
                'me' => ['plain' => 'plain'],
                'key' => '42',
            ]]]);
    });

    it('names a type after its class without a Type suffix, unless named explicitly', function () {
        expect(discoveredTypes(FieldSourcesType::class)[0]->name)->toBe('FieldSources')
            ->and(discoveredTypes(NamedType::class)[0])
            ->name->toBe('Renamed')
            ->description->toBe('Named explicitly');
    });
});

describe('the instantiable guard', function () {
    it('lets an abstract #[Type] through, but still skips its actions', function () {
        $items = iterator_to_array(discoverGraphQL(AbstractShape::class)->getItems(), false);

        expect($items)->toHaveCount(1)
            ->and($items[0])->toBeInstanceOf(DiscoveredType::class)
            ->and($items[0]->name)->toBe('AbstractShape')
            ->and(array_filter($items, static fn(mixed $item): bool => $item instanceof DiscoveredAction))->toBe([]);
    });

    it('passes enums to discovery', function () {
        expect(iterator_to_array(discoverGraphQL(PlainEnum::class)->getItems(), false))->toBe([])
            ->and(fn() => discoverGraphQL(Invalid\TypeOnEnum::class))
            ->toThrow(LogicException::class, '#[Type] on the enum ' . Invalid\TypeOnEnum::class . ' is not supported');
    });
});

describe('rejections', function () {
    it('rejects shapes that cannot become a field', function (object $shape, string $format) {
        expectRejected($shape, $format);
    })->with([
        '#[Field] on a private property' => [
            fn() => new #[Type] class {
                #[Field]
                private string $secret = 'secret';

                public function secret(): string
                {
                    return $this->secret;
                }
            },
            'Property %1$s::$secret has #[Field] but is not public.',
        ],
        '#[Field] on a static property' => [
            fn() => new #[Type] class {
                #[Field]
                public static string $shared = 'shared';
            },
            'Property %1$s::$shared has #[Field] but is static.',
        ],
        '#[Field] on a protected method' => [
            fn() => new #[Type] class {
                #[Field]
                protected function hidden(): string
                {
                    return 'hidden';
                }
            },
            'Method %1$s::hidden() has #[Field] but is not public.',
        ],
        '#[Field] on a write-only property' => [
            fn() => new #[Type] class {
                public string $stored = '';

                #[Field]
                public string $label {
                    set(string $value) {
                        $this->stored = $value;
                    }
                }
            },
            'Property %1$s::$label has #[Field] but no get hook',
        ],
        '#[Field] and #[Ignore] on a promoted property' => [
            fn() => new #[Type] class {
                public function __construct(
                    #[Field, Ignore]
                    public string $label = 'label',
                ) {}
            },
            'Property %1$s::$label has both #[Field] and #[Ignore]. Remove one.',
        ],
        '#[Field] and #[Ignore] on a method' => [
            fn() => new #[Type] class {
                #[Field, Ignore]
                public function label(): string
                {
                    return 'label';
                }
            },
            'Method %1$s::label() has both #[Field] and #[Ignore]. Remove one.',
        ],
        'a PHP union' => [
            fn() => new #[Type] class {
                public int|string $key = 1;
            },
            'Property %1$s::$key has the union type string|int, which has no GraphQL type.',
        ],
        'both type: and of:' => [
            fn() => new #[Type] class {
                /** @var list<string> */
                #[Field(type: 'String', of: 'string')]
                public array $tags = [];
            },
            'Property %1$s::$tags sets both type: and of: on #[Field].',
        ],
        '#[Arg(rules:)] on a method field' => [
            fn() => new #[Type] class {
                #[Field]
                public function excerpt(#[Arg(rules: ['min:1'])] int $length): string
                {
                    return str_repeat('x', $length);
                }
            },
            'Method %1$s::excerpt() has #[Arg(rules:)] on $length, but field args are not validated yet.',
        ],
        '#[Field] on a static method' => [
            fn() => new #[Type] class {
                #[Field]
                public static function shared(): string
                {
                    return 'shared';
                }
            },
            'Method %1$s::shared() has #[Field] but is static.',
        ],
        'an action attribute on a method field' => [
            fn() => new #[Type] class {
                /** @return list<string> */
                #[Field(of: 'string')]
                #[Paginated]
                public function pages(Pagination $page): array
                {
                    return [];
                }
            },
            'Method %1$s::pages() has #[Paginated], which only applies to #[Query] and #[Mutation] methods.',
        ],
        'a model-bound parameter on a method field' => [
            fn() => new #[Type] class {
                #[Field]
                public function ownerName(User $owner): string
                {
                    return $owner->name;
                }
            },
            'Method %1$s::ownerName() binds the model parameter $owner, which fields do not support yet.',
        ],
        'two fields with one name' => [
            fn() => new #[Type] class {
                public string $label = 'label';

                #[Field(name: 'label')]
                public function computedLabel(): string
                {
                    return 'computed';
                }
            },
            'Type %1$s has two fields named "label" ($label and computedLabel()).',
        ],
    ]);

    it('rejects #[Type] on a Rebing type', function () {
        expect(fn() => discoverGraphQL(Invalid\TypeOnRebingType::class))
            ->toThrow(LogicException::class, '#[Type] on ' . Invalid\TypeOnRebingType::class . ', which extends Rebing\GraphQL\Support\Type');
    });

    it('rejects two types with the same GraphQL name, naming both classes', function () {
        expect(fn() => discoverGraphQL(Invalid\DuplicateNameOne::class, Invalid\DuplicateNameTwo::class))
            ->toThrow(LogicException::class, sprintf(
                'GraphQL type name [Duplicate] is used by both %s and %s. Rename one with #[Type(name: ...)].',
                Invalid\DuplicateNameOne::class,
                Invalid\DuplicateNameTwo::class,
            ));
    });

    it('rejects a type named like a hand-written Rebing type', function () {
        isolateGraphQL();

        expect(fn() => discoverGraphQL(PamphletType::class, PamphletQuery::class, Invalid\ClashesWithRebingType::class)->apply())
            ->toThrow(LogicException::class, sprintf(
                'GraphQL type name [Pamphlet] is used by both %s (#[Type]) and the Rebing type %s.',
                Invalid\ClashesWithRebingType::class,
                PamphletType::class,
            ));
    });
});

it('resolves a list of a few thousand objects quickly', function () {
    schemaSdl(RowsQuery::class, Row::class);

    $this->postJson('/graphql', ['query' => '{ rows(count: 1) { id } }'])->assertOk();

    $start = hrtime(true);
    $response = $this->postJson('/graphql', ['query' => '{ rows(count: 5000) { id name note score active label } }']);
    $elapsedMs = (hrtime(true) - $start) / 1e6;

    $response->assertOk()
        ->assertJsonMissingPath('errors')
        ->assertJsonCount(5000, 'data.rows')
        ->assertJsonPath('data.rows.4999.label', 'row 5000');

    expect($elapsedMs)->toBeLessThan(1500);
});

it('leaves the workbench schema unchanged', function () {
    $sorted = ['sortArguments' => true, 'sortEnumValues' => true, 'sortFields' => true, 'sortInputFields' => true, 'sortTypes' => true];
    $snapshot = BuildSchema::build((string) file_get_contents(__DIR__ . '/../../Fixtures/RebingGraphQL/workbench-schema.graphql'));

    expect(SchemaPrinter::doPrint(GraphQL::schema(), $sorted))->toBe(SchemaPrinter::doPrint($snapshot, $sorted));
});
