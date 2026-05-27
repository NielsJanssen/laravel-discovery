<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredAction;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\GraphQLDiscovery;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Pagination;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use Tests\Fixtures\RebingGraphQL\DuplicateArgProviderQuery;
use Tests\Fixtures\RebingGraphQL\PaginatedWithCustomLimitQuery;
use Tests\Fixtures\RebingGraphQL\PaginatedWithValueObjectQuery;
use Tests\Fixtures\RebingGraphQL\SortableSeparateQuery;
use Tests\Fixtures\RebingGraphQL\SortableUnifiedQuery;

function discoverArgProviderFixture(string ...$classes): GraphQLDiscovery
{
    $discovery = app(GraphQLDiscovery::class);
    $discovery->setItems(new DiscoveryItems());

    $location = new DiscoveryLocation(
        namespace: 'Tests\\Fixtures\\GraphQL',
        path: dirname(__DIR__, 2) . '/Fixtures/RebingGraphQL',
    );

    foreach ($classes as $class) {
        $discovery->discover($location, new ClassReflector($class));
    }

    return $discovery;
}

describe('ActionArgProvider hook', function () {
    it('lets #[Paginated] declare page+limit args and binds a Pagination value object to a method parameter', function () {
        $items = iterator_to_array(discoverArgProviderFixture(PaginatedWithValueObjectQuery::class)->getItems());
        /** @var DiscoveredAction $item */
        $item = $items[0];

        expect($item->argCompositions)->toBe(['pagination' => Pagination::class])
            ->and($item->args)->toBeEmpty();

        $field = $item->createType(app());
        $fieldArgs = $field->args();

        expect($fieldArgs)->toHaveKeys(['page', 'limit'])
            ->and((string) $fieldArgs['page']['type'])->toBe('Int')
            ->and($fieldArgs['page']['defaultValue'])->toBe(1)
            ->and($fieldArgs['limit']['defaultValue'])->toBe(20);
    });

    it('honours a configurable defaultLimit on #[Paginated]', function () {
        $items = iterator_to_array(discoverArgProviderFixture(PaginatedWithCustomLimitQuery::class)->getItems());
        /** @var DiscoveredAction $item */
        $item = $items[0];

        $fieldArgs = $item->createType(app())->args();

        expect($fieldArgs['limit']['defaultValue'])->toBe(50);
    });

    it('constructs the Pagination value object from the GraphQL args at resolve time', function () {
        $items = iterator_to_array(discoverArgProviderFixture(PaginatedWithValueObjectQuery::class)->getItems());
        $field = $items[0]->createType(app());

        $result = $field->resolve(null, ['page' => 3, 'limit' => 5], null, null);

        expect($result->currentPage())->toBe(3)
            ->and($result->perPage())->toBe(5);
    });

    it('declares two args plus an "in:" rule when #[Sortable] is used in separate mode', function () {
        $items = iterator_to_array(discoverArgProviderFixture(SortableSeparateQuery::class)->getItems());
        /** @var DiscoveredAction $item */
        $item = $items[0];

        $fieldArgs = $item->createType(app())->args();

        expect($fieldArgs)->toHaveKeys(['sortBy', 'sortDirection'])
            ->and($fieldArgs['sortBy']['rules'])->toBe(['nullable', 'in:title,author'])
            ->and($fieldArgs['sortDirection']['rules'])->toBe(['nullable', 'in:asc,desc'])
            ->and($fieldArgs['sortDirection']['defaultValue'])->toBe('asc');
    });

    it('builds the Sort value object from sortBy + sortDirection args', function () {
        $items = iterator_to_array(discoverArgProviderFixture(SortableSeparateQuery::class)->getItems());
        $field = $items[0]->createType(app());

        $result = $field->resolve(null, ['sortBy' => 'author', 'sortDirection' => 'desc'], null, null);

        expect($result)->toBe('author:desc');
    });

    it('declares a single "order" arg in unified mode and parses field:direction at resolve time', function () {
        $items = iterator_to_array(discoverArgProviderFixture(SortableUnifiedQuery::class)->getItems());
        $field = $items[0]->createType(app());
        $fieldArgs = $field->args();

        expect($fieldArgs)->toHaveKey('order')
            ->and($fieldArgs['order']['rules'])->toBe(['nullable', 'in:title:asc,title:desc'])
            ->and($fieldArgs)->not->toHaveKey('sortBy');

        expect($field->resolve(null, ['order' => 'title:desc'], null, null))->toBe('title:desc');
    });

    it('throws during discovery when two ActionArgProviders declare the same arg name', function () {
        expect(fn() => discoverArgProviderFixture(DuplicateArgProviderQuery::class))
            ->toThrow(\RuntimeException::class, 'declaring the same arg "shared"');
    });
});

describe('Paginated end-to-end via the workbench', function () {
    it('paginates books with default page/limit args', function () {
        $this->postJson('/graphql', [
            'query' => '{ paginatedBooks(page: 1, limit: 2) { data { id title } total per_page current_page } }',
        ])
            ->assertOk()
            ->assertJsonPath('data.paginatedBooks.total', 3)
            ->assertJsonPath('data.paginatedBooks.per_page', 2)
            ->assertJsonPath('data.paginatedBooks.current_page', 1)
            ->assertJsonCount(2, 'data.paginatedBooks.data');
    });

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
