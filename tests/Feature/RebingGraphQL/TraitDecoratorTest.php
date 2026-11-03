<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredAction;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Paginated;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Pagination;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use RuntimeException;
use Tests\Fixtures\RebingGraphQL\AlwaysAllowGate;
use Tests\Fixtures\RebingGraphQL\AlwaysDenyGate;
use Tests\Fixtures\RebingGraphQL\Traits\PaginatesBooks;
use Tests\Fixtures\RebingGraphQL\Traits\RequiresLogin;
use Tests\Fixtures\RebingGraphQL\Traits\TraitAuthorizedQuery;
use Tests\Fixtures\RebingGraphQL\Traits\TraitDecoratedQueries;
use Tests\Fixtures\RebingGraphQL\Traits\TraitOverriddenQueries;
use Tests\Fixtures\RebingGraphQL\Traits\TraitPaginatedQueries;
use Workbench\App\GraphQL\Middleware\ExclamationMiddleware;
use Workbench\App\GraphQL\Middleware\UppercaseMiddleware;
use Workbench\App\Models\User;

/** @return list<class-string|null> */
function gatesOf(DiscoveredAction $action): array
{
    return array_map(static fn(Authorize $authorize): ?string => $authorize->gate, $action->authorizations);
}

it('applies the decorators of the traits a class uses, the first trait winning', function () {
    $ping = discoveredActions(TraitDecoratedQueries::class)['ping'];

    expect($ping->action->schema)->toBe('admin')
        ->and($ping->middleware)->toBe([ExclamationMiddleware::class, UppercaseMiddleware::class])
        ->and(gatesOf($ping))->toBe([AlwaysDenyGate::class, AlwaysAllowGate::class]);
});

it('lets the class own attributes come before its traits, and the method win over both', function () {
    $actions = discoveredActions(TraitOverriddenQueries::class);

    expect($actions['ping']->action->schema)->toBe('public')
        ->and($actions['methodWins']->action->schema)->toBe('reports')
        ->and($actions['ping']->middleware)->toBe([UppercaseMiddleware::class, ExclamationMiddleware::class])
        ->and(gatesOf($actions['ping']))->toBe([AlwaysAllowGate::class, AlwaysDenyGate::class]);
});

it('reads a type builder and arg provider from a trait', function () {
    $books = discoveredActions(TraitPaginatedQueries::class)['books'];

    expect($books->typeBuilder)->toBeInstanceOf(Paginated::class)
        ->and($books->typeBuilder->defaultLimit)->toBe(30)
        ->and($books->argProviders)->toEqual([new Paginated(defaultLimit: 30)]);
});

it('does not read #[Type] or #[Input] from a trait', function () {
    expect(discoveredTypes(TraitDecoratedQueries::class))->toBe([]);
});

it('keeps trait attributes through a discovery cache round trip', function () {
    $actions = [];

    foreach (cachedGraphQLItems(TraitDecoratedQueries::class) as $item) {
        if ($item instanceof DiscoveredAction) {
            $actions[$item->method] = $item;
        }
    }

    expect($actions['ping']->action->schema)->toBe('admin')
        ->and($actions['ping']->middleware)->toBe([ExclamationMiddleware::class, UppercaseMiddleware::class])
        ->and(gatesOf($actions['ping']))->toBe([AlwaysDenyGate::class, AlwaysAllowGate::class]);
});

it('enforces an #[Authorize] from a trait over a real request from the discovery cache', function () {
    applyCachedGraphQL([TraitAuthorizedQuery::class]);

    expect(queryGraphQL('{ traitLoggedIn }')['errors'][0]['message'])->toBe('Login required');

    $this->actingAs(new User());

    expect(queryGraphQL('{ traitLoggedIn }')['data'])->toBe(['traitLoggedIn' => 'ok']);
});

it('rejects them at discovery', fn(object $shape, string $format, string $exception) => expectRejected($shape, $format, $exception))->with([
    'a type builder on both the class and a trait' => [
        fn() => new #[Paginated] class {
            use PaginatesBooks;

            #[Query(type: 'Book')]
            public function books(Pagination $pagination): LengthAwarePaginator
            {
                throw new RuntimeException('unreachable');
            }
        },
        'Class %1$s has multiple ActionTypeBuilder attributes (' . Paginated::class . ', ' . Paginated::class . '), counting the traits it uses.',
        RuntimeException::class,
    ],
    'a field decorator from a trait on a #[Type] without actions' => [
        fn() => new #[Type] class {
            use RequiresLogin;

            public string $name = 'Acme';
        },
        '#[Authorize] on the #[Type] class %1$s has nothing to apply to',
        \LogicException::class,
    ],
]);
