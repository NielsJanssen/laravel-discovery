<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredAction;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\GraphQLDiscovery;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Paginated;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use Tests\Fixtures\RebingGraphQL\CustomBuilderQuery;
use Tests\Fixtures\RebingGraphQL\DuplicateBuilderQuery;
use Tests\Fixtures\RebingGraphQL\PaginatedBookQuery;
use Tests\Fixtures\RebingGraphQL\PaginatedScalarReturnQuery;
use Tests\Fixtures\RebingGraphQL\WrapInListBuilder;

function discoverBuilderFixture(string ...$classes): GraphQLDiscovery
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

describe('ActionTypeBuilder hook', function () {
    it('attaches a method-level builder to the discovered action', function () {
        $items = iterator_to_array(discoverBuilderFixture(PaginatedBookQuery::class)->getItems());
        /** @var DiscoveredAction $item */
        $item = $items[0];

        expect($item->typeBuilder)->toBeInstanceOf(Paginated::class);
    });

    it('delegates to consumer-defined ActionTypeBuilder attributes for fully custom return types', function () {
        $items = iterator_to_array(discoverBuilderFixture(CustomBuilderQuery::class)->getItems());
        /** @var DiscoveredAction $item */
        $item = $items[0];

        expect($item->typeBuilder)->toBeInstanceOf(WrapInListBuilder::class);

        $field = $item->createType(app());

        expect((string) $field->type())->toBe('[String!]');
    });

    it('throws during discovery when more than one ActionTypeBuilder is attached to a method', function () {
        expect(fn() => discoverBuilderFixture(DuplicateBuilderQuery::class))
            ->toThrow(\RuntimeException::class, 'multiple ActionTypeBuilder attributes');
    });

    it('resolves a #[Paginated] query end-to-end through Rebing\'s pagination wrapper', function () {
        $this->postJson('/graphql', [
            'query' => '{ paginatedBooks(page: 1, perPage: 2) { data { id title } total per_page current_page last_page } }',
        ])
            ->assertOk()
            ->assertJsonPath('data.paginatedBooks.total', 3)
            ->assertJsonPath('data.paginatedBooks.per_page', 2)
            ->assertJsonPath('data.paginatedBooks.current_page', 1)
            ->assertJsonPath('data.paginatedBooks.last_page', 2)
            ->assertJsonCount(2, 'data.paginatedBooks.data')
            ->assertJsonPath('data.paginatedBooks.data.0.title', 'The Great Gatsby')
            ->assertJsonPath('data.paginatedBooks.data.1.title', '1984');
    });

    it('throws from #[Paginated] when the action has no explicit object type', function () {
        $items = iterator_to_array(discoverBuilderFixture(PaginatedScalarReturnQuery::class)->getItems());
        /** @var DiscoveredAction $item */
        $item = $items[0];

        $field = $item->createType(app());

        expect(fn() => $field->type())
            ->toThrow(\RuntimeException::class, '#[Paginated] requires an explicit object type');
    });
});
