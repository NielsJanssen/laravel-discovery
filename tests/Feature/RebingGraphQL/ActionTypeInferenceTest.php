<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use GraphQL\Utils\SchemaPrinter;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRef;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Tests\Fixtures\RebingGraphQL\Types\Inference\ExplicitArgTypesQuery;
use Tests\Fixtures\RebingGraphQL\Types\Inference\Invalid;
use Tests\Fixtures\RebingGraphQL\Types\Inference\Novel;
use Tests\Fixtures\RebingGraphQL\Types\Inference\NovelQuery;
use Tests\Fixtures\RebingGraphQL\Types\PamphletType;

afterEach(function () {
    app()->forgetInstance('config_loaded_from_cache');
});

/**
 * The default schema built from the given classes, printed with types and fields sorted.
 *
 * @param  class-string  ...$classes
 */
function sortedSchemaSdl(string ...$classes): string
{
    schemaSdl(...$classes);

    return SchemaPrinter::doPrint(GraphQL::schema(), ['sortTypes' => true, 'sortFields' => true, 'sortArguments' => true]);
}

const INFERRED_SDL = <<<'GRAPHQL'
    type Mutation {
      renameNovel(title: String!): Novel!
    }

    type Novel {
      sequel: Novel
      title: String!
    }

    type NovelPagination {
      "Current page of the cursor"
      current_page: Int!

      "List of items on the current page"
      data: [Novel!]!

      "Number of the first item returned"
      from: Int

      "Determines if cursor has more pages after the current page"
      has_more_pages: Boolean!

      "The last page (number of pages)"
      last_page: Int!

      "Number of items returned per page"
      per_page: Int!

      "Number of the last item returned"
      to: Int

      "Number of total items selected by the query"
      total: Int!
    }

    type Query {
      maybeNovel: Novel
      maybeNovelPage(limit: Int = 20, page: Int = 1): NovelPagination
      novel: Novel!
      novelCollection: [Novel!]
      novelPage(limit: Int = 20, page: Int = 1): NovelPagination!
      novels: [Novel!]!
      optionalNovelPage(limit: Int = 20, page: Int = 1): NovelPagination
      sparseNovels: [Novel]!
      widenedNovel: Novel
    }

    GRAPHQL;

describe('return type inference', function () {
    it('infers a #[Type] class return, of: lists and a #[Paginated] class-string', function () {
        expect(sortedSchemaSdl(NovelQuery::class, Novel::class))->toBe(INFERRED_SDL);

        buildAllSchemas();
    });

    it('does not depend on the order the query and the type are discovered in', function () {
        expect(sortedSchemaSdl(NovelQuery::class, Novel::class))
            ->toBe(sortedSchemaSdl(Novel::class, NovelQuery::class));
    });

    it('stores an inferred class return as a class-string reference', function () {
        $actions = discoveredActions(NovelQuery::class);

        expect($actions['novel']->action->type)->toBe(Novel::class)
            ->and($actions['novel']->returnType)->toEqual(TypeRef::class(Novel::class))
            ->and($actions['maybeNovel']->returnType)->toEqual(TypeRef::class(Novel::class, nullable: true))
            ->and($actions['widenedNovel']->returnType)->toEqual(TypeRef::class(Novel::class, nullable: true))
            ->and($actions['renameNovel']->returnType)->toEqual(TypeRef::class(Novel::class))
            ->and(unserialize(serialize($actions['novel'])))->toEqual($actions['novel']);
    });

    it('resolves queries and mutations returning a discovered type without type:', function () {
        schemaSdl(NovelQuery::class, Novel::class);

        $this->postJson('/graphql', ['query' => <<<'GRAPHQL'
            {
              novel { title sequel { title sequel { title } } }
              maybeNovel { title }
              widenedNovel { title }
              novels { title }
              sparseNovels { title }
              novelCollection { title }
              novelPage(limit: 5) { data { title } total per_page }
            }
            GRAPHQL])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertExactJson(['data' => [
                'novel' => ['title' => 'Dune', 'sequel' => ['title' => 'Dune Messiah', 'sequel' => null]],
                'maybeNovel' => null,
                'widenedNovel' => ['title' => 'Children of Dune'],
                'novels' => [['title' => 'Dune'], ['title' => 'Dune Messiah']],
                'sparseNovels' => [['title' => 'Dune'], null],
                'novelCollection' => [['title' => 'God Emperor of Dune']],
                'novelPage' => ['data' => [['title' => 'Heretics of Dune']], 'total' => 1, 'per_page' => 5],
            ]]);

        $this->postJson('/graphql', ['query' => 'mutation { renameNovel(title: "Dune") { title } }'])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertExactJson(['data' => ['renameNovel' => ['title' => 'Dune']]]);
    });
});

describe('explicit arg types', function () {
    it('resolves #[Arg(type:)] scalar and list names, nullable when the parameter is', function () {
        expect(schemaSdl(ExplicitArgTypesQuery::class))->toContain(
            'explicit(id: ID!, maybeId: ID, label: String!, maybeLabel: String, tags: [String!]!, maybeTags: [String!]): String!',
        );

        buildAllSchemas();

        $this->postJson('/graphql', ['query' => '{ explicit(id: 7, label: "x", tags: ["a", "b"]) }'])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertExactJson(['data' => ['explicit' => '7,-,x,-,a+b,']]);
    });
});

describe('unsupported returns', function () {
    it('names the method and the ways to give it a type', function (string $class, string $message) {
        expect(fn() => discoverGraphQL($class))->toThrow(\RuntimeException::class, $message);
    })->with([
        'a PHP union' => [
            Invalid\UnionReturnQuery::class,
            'Method ' . Invalid\UnionReturnQuery::class . '::key has the union type string|int, which has no GraphQL type. Name one with #[Query(type: ...)]; union output types need a #[Union] marker. A scalar, void, enum or #[Type] class return type is inferred.',
        ],
        'mixed' => [
            Invalid\MixedReturnQuery::class,
            'Method ' . Invalid\MixedReturnQuery::class . '::anything declares the type mixed. Add a PHP type, or name the GraphQL type with #[Query(type: ...)]. A scalar, void, enum or #[Type] class return type is inferred.',
        ],
    ]);
});

describe('unregistered class references', function () {
    it('rejects them when applied, naming the referencing member', function (array $classes, string $message, bool $cached) {
        isolateGraphQL();

        if ($cached) {
            app()->instance('config_loaded_from_cache', true);
        }

        expect(fn() => discoverGraphQL(...$classes)->apply())->toThrow(\LogicException::class, $message);
    })->with([
        'an inferred action return' => [
            [Invalid\UnregisteredReturnQuery::class],
            'Method ' . Invalid\UnregisteredReturnQuery::class . '::thing references ' . Invalid\Unregistered::class . ', which is not a registered GraphQL output type. Add #[Type] to Unregistered, or name a registered GraphQL type with type: (or of: for a list) on #[Query].',
        ],
        'of: on a mutation' => [
            [Invalid\UnregisteredListMutation::class],
            'Method ' . Invalid\UnregisteredListMutation::class . '::things references ' . Invalid\Unregistered::class . ', which is not a registered GraphQL output type. Add #[Type] to Unregistered, or name a registered GraphQL type with type: (or of: for a list) on #[Mutation].',
        ],
        'a hand-written Rebing type class' => [
            [Invalid\RebingClassQuery::class],
            'Method ' . Invalid\RebingClassQuery::class . '::pamphlet references the Rebing type class ' . PamphletType::class . '. Reference a hand-written Rebing type by its GraphQL name with type: (or of: for a list) on #[Query].',
        ],
        'a type field' => [
            [Invalid\UnregisteredField::class],
            'Field UnregisteredField.thing references ' . Invalid\Unregistered::class . ', which is not a registered GraphQL output type. Add #[Type] to Unregistered, or name a registered GraphQL type with type: (or of: for a list) on #[Field].',
        ],
        'an object type as an action arg' => [
            [Invalid\ObjectTypeArgQuery::class, Novel::class],
            'Argument novel of method ' . Invalid\ObjectTypeArgQuery::class . '::review references ' . Novel::class . ', which is not a registered GraphQL input type (it is registered as object type [Novel]). Use a scalar or an enum, or name a registered GraphQL input type with #[Arg(type: ...)].',
        ],
        'an unregistered class as an action arg' => [
            [Invalid\UnregisteredArgQuery::class],
            'Argument thing of method ' . Invalid\UnregisteredArgQuery::class . '::find references ' . Invalid\Unregistered::class . ', which is not a registered GraphQL input type. Use a scalar or an enum, or name a registered GraphQL input type with #[Arg(type: ...)].',
        ],
        'an unregistered class as a field arg' => [
            [Invalid\UnregisteredFieldArg::class],
            'Argument thing of field UnregisteredFieldArg.label references ' . Invalid\Unregistered::class . ', which is not a registered GraphQL input type. Use a scalar or an enum, or name a registered GraphQL input type with #[Arg(type: ...)].',
        ],
    ])->with([
        'config written' => [false],
        'config cached' => [true],
    ]);
});
