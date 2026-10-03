<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Paginated;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use Tests\Fixtures\RebingGraphQL\CustomBuilderQuery;
use Tests\Fixtures\RebingGraphQL\NonNullBuilderQuery;
use Tests\Fixtures\RebingGraphQL\PaginatedInvalidReturns;
use Tests\Fixtures\RebingGraphQL\WrapInListBuilder;

describe('ActionTypeBuilder hook', function () {
    it('delegates to consumer-defined ActionTypeBuilder attributes for fully custom return types', function () {
        $item = discoveredActions(CustomBuilderQuery::class)['resolve'];

        expect($item->typeBuilder)->toBeInstanceOf(WrapInListBuilder::class)
            ->and((string) $item->createType(app())->type())->toBe('[String!]!');
    });

    it('rejects more than one ActionTypeBuilder on a method at discovery', function () {
        expectRejected(
            new class {
                #[Query(type: 'Book')]
                #[Paginated]
                #[WrapInListBuilder]
                public function books(): mixed
                {
                    return null;
                }
            },
            'Method %1$s::books has multiple ActionTypeBuilder attributes (',
            exception: \RuntimeException::class,
        );
    });

    it('resolves a #[Paginated] query end-to-end through Rebing\'s pagination wrapper', function () {
        $this->postJson('/graphql', [
            'query' => '{ paginatedBooks(page: 1, limit: 2) { data { id title } total per_page current_page last_page } }',
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

    it('leaves a builder-built type that is already non-null as it is', function () {
        $item = discoveredActions(NonNullBuilderQuery::class)['resolve'];

        expect((string) $item->createType(app())->type())->toBe('[String!]!');
    });

    it('throws from #[Paginated] when the action has no explicit object type', function (string $method) {
        $item = discoveredActions(PaginatedInvalidReturns::class)[$method];

        expect(fn() => $item->createType(app())->type())
            ->toThrow(\RuntimeException::class, '#[Paginated] requires an explicit object type');
    })->with(['a paginator return' => 'contractReturn', 'a scalar return' => 'scalarReturn']);

    it('leaves the return type unset for a paginator return, so the error points at type:', function () {
        expect(discoveredActions(PaginatedInvalidReturns::class)['contractReturn']->returnType)->toBeNull();
    });
});
