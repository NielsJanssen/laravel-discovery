<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Pagination;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Sort;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Sortable;
use Tests\Fixtures\RebingGraphQL\DuplicateArgProviderQuery;
use Tests\Fixtures\RebingGraphQL\PaginatedActions;
use Tests\Fixtures\RebingGraphQL\SortableQueries;

describe('ActionArgProvider hook', function () {
    it('lets #[Paginated] declare page+limit args and binds a Pagination value object to a method parameter', function () {
        $item = discoveredActions(PaginatedActions::class)['valueObject'];

        expect($item->parameters->argCompositions)->toBe(['pagination' => Pagination::class])
            ->and($item->parameters->args)->toBeEmpty();

        $fieldArgs = $item->createType(app())->args();

        expect($fieldArgs)->toHaveKeys(['page', 'limit'])
            ->and((string) $fieldArgs['page']['type'])->toBe('Int')
            ->and($fieldArgs['page']['defaultValue'])->toBe(1)
            ->and($fieldArgs['limit']['defaultValue'])->toBe(20);
    });

    it('honours a configurable defaultLimit on #[Paginated]', function () {
        $fieldArgs = discoveredActions(PaginatedActions::class)['customLimit']->createType(app())->args();

        expect($fieldArgs['limit']['defaultValue'])->toBe(50);
    });

    it('constructs the Pagination value object from the GraphQL args at resolve time', function () {
        $field = discoveredActions(PaginatedActions::class)['valueObject']->createType(app());

        $result = $field->resolve(null, ['page' => 3, 'limit' => 5], null, null);

        expect($result->currentPage())->toBe(3)
            ->and($result->perPage())->toBe(5);
    });

    it('declares two args plus an "in:" rule when #[Sortable] is used in separate mode, defaulting only the direction', function () {
        $fieldArgs = discoveredActions(SortableQueries::class)['separate']->createType(app())->args();

        expect($fieldArgs)->toHaveKeys(['sortBy', 'sortDirection'])
            ->and($fieldArgs['sortBy']['rules'])->toBe(['nullable', 'in:title,author'])
            ->and($fieldArgs['sortDirection']['rules'])->toBe(['nullable', 'in:asc,desc'])
            ->and($fieldArgs['sortDirection']['defaultValue'])->toBe('asc')
            ->and($fieldArgs['sortBy'])->not->toHaveKey('defaultValue');
    });

    it('builds the Sort value object from sortBy + sortDirection args', function () {
        $field = discoveredActions(SortableQueries::class)['separate']->createType(app());

        $result = $field->resolve(null, ['sortBy' => 'author', 'sortDirection' => 'desc'], null, null);

        expect($result)->toBe('author:desc');
    });

    it('declares a single "order" arg in unified mode and parses field:direction at resolve time', function () {
        $field = discoveredActions(SortableQueries::class)['unified']->createType(app());
        $fieldArgs = $field->args();

        expect($fieldArgs)->toHaveKey('order')
            ->and($fieldArgs['order']['rules'])->toBe(['nullable', 'in:title:asc,title:desc'])
            ->and($fieldArgs)->not->toHaveKey('sortBy')
            ->and($field->resolve(null, ['order' => 'title:desc'], null, null))->toBe('title:desc');
    });
});

describe('Paginated end-to-end via the workbench', function () {
    it('orders books by the Sort value object in descending order', function () {
        $this->postJson('/graphql', [
            'query' => '{ paginatedBooks(page: 1, limit: 3, sortBy: "title", sortDirection: "desc") { data { title } } }',
        ])
            ->assertOk()
            ->assertJsonPath('data.paginatedBooks.data.0.title', 'To Kill a Mockingbird')
            ->assertJsonPath('data.paginatedBooks.data.1.title', 'The Great Gatsby')
            ->assertJsonPath('data.paginatedBooks.data.2.title', '1984');
    });
});

describe('#[Sortable(defaultField:)]', function () {
    it('defaults the sortBy arg in separate mode', function () {
        $field = discoveredActions(SortableQueries::class)['separateDefault']->createType(app());

        expect($field->args()['sortBy']['defaultValue'])->toBe('author')
            ->and($field->resolve(null, ['sortBy' => 'author', 'sortDirection' => 'asc'], null, null))->toBe('author:asc');
    });

    it('defaults the order arg to field:direction in unified mode', function () {
        $field = discoveredActions(SortableQueries::class)['unifiedDefault']->createType(app());

        expect($field->args()['order']['defaultValue'])->toBe('title:desc');
    });
});

describe('#[Sortable(defaultField:)] end-to-end', function () {
    it('sorts by the default when the caller passes no sort', function () {
        $this->postJson('/graphql', ['query' => '{ sortedBooks }'])
            ->assertOk()
            ->assertJsonPath('data.sortedBooks', 'title:desc');
    });

    it('lets the caller override the default', function () {
        $this->postJson('/graphql', ['query' => '{ sortedBooks(sortBy: "author", sortDirection: "asc") }'])
            ->assertOk()
            ->assertJsonPath('data.sortedBooks', 'author:asc');
    });
});

describe('rejected argument providers', function () {
    it('rejects them at discovery', function (string|object $shape, string $format, string $exception) {
        expectRejected($shape, $format, exception: $exception);
    })->with([
        'two providers declaring the same arg' => [
            DuplicateArgProviderQuery::class,
            'Method %1$s::resolve has multiple ActionArgProvider attributes declaring the same arg "shared".',
            \RuntimeException::class,
        ],
        'a default direction that is neither asc nor desc' => [
            fn() => new class {
                #[Query]
                #[Sortable(['title'], defaultDirection: 'descending')]
                public function sorted(Sort $sort): string
                {
                    return $sort->direction;
                }
            },
            "#[Sortable(defaultDirection: 'descending')] must be asc or desc.",
            \LogicException::class,
        ],
        'a default that is not sortable' => [
            fn() => new class {
                #[Query]
                #[Sortable(['title'], defaultField: 'published_at')]
                public function sorted(Sort $sort): string
                {
                    return $sort->direction;
                }
            },
            "#[Sortable(defaultField: 'published_at')] is not one of the sortable fields: title.",
            \LogicException::class,
        ],
    ]);
});
