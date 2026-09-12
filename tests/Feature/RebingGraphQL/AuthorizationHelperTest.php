<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use Illuminate\Support\Facades\Gate;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorization;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredAction;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\GraphQLDiscovery;
use Rebing\GraphQL\Error\AuthorizationError;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use Tests\Fixtures\RebingGraphQL\AuthorizationHelperQuery;
use Tests\Fixtures\RebingGraphQL\ReportAbility;
use Workbench\App\Models\User;

/**
 * @return array<string, DiscoveredAction>
 */
function discoverAuthorizationHelper(): array
{
    $discovery = app(GraphQLDiscovery::class);
    $discovery->setItems(new DiscoveryItems());

    $location = new DiscoveryLocation(
        namespace: 'Tests\\Fixtures\\GraphQL',
        path: dirname(__DIR__, 2) . '/Fixtures/RebingGraphQL',
    );

    $discovery->discover($location, new ClassReflector(AuthorizationHelperQuery::class));

    $byMethod = [];

    foreach ($discovery->getItems() as $item) {
        /** @var DiscoveredAction $item */
        $byMethod[$item->method] = $item;
    }

    return $byMethod;
}

describe('Authorization::policy()', function () {
    beforeEach(function () {
        $this->actingAs(new User());
    });

    it('returns without throwing when the gate grants the ability', function () {
        Gate::define('view-report', fn(User $user) => true);

        expect(fn() => new Authorization()->authorize('view-report'))->not->toThrow(AuthorizationError::class);
    });

    it('throws a client-safe authorization error when the gate denies', function () {
        Gate::define('view-report', fn(User $user) => false);

        try {
            new Authorization()->authorize('view-report');
            $this->fail('Expected an AuthorizationError.');
        } catch (AuthorizationError $e) {
            expect($e->getMessage())->toBe('Forbidden')
                ->and($e->isClientSafe())->toBeTrue()
                ->and($e->getCategory())->toBe('authorization');
        }
    });

    it('reports a custom message when one is given', function () {
        Gate::define('view-report', fn(User $user) => false);

        expect(fn() => new Authorization()->authorize('view-report', message: 'Not your report'))
            ->toThrow(AuthorizationError::class, 'Not your report');
    });

    it('hands the arguments to the gate', function () {
        Gate::define('view', fn(User $actor, User $subject) => $subject->name === 'Ada Lovelace');

        $allowed = User::factory()->makeOne(['name' => 'Ada Lovelace']);
        $denied = User::factory()->makeOne(['name' => 'Grace Hopper']);

        expect(fn() => new Authorization()->authorize('view', $allowed))->not->toThrow(AuthorizationError::class)
            ->and(fn() => new Authorization()->authorize('view', $denied))->toThrow(AuthorizationError::class);
    });

    it('requires every ability when given a list', function () {
        Gate::define('view-report', fn(User $user) => true);
        Gate::define('export-report', fn(User $user) => false);

        expect(fn() => new Authorization()->authorize(['view-report', 'export-report']))
            ->toThrow(AuthorizationError::class)
            ->and(fn() => new Authorization()->authorize(['view-report']))
            ->not->toThrow(AuthorizationError::class);
    });

    it('accepts a backed enum as the ability', function () {
        Gate::define(ReportAbility::View->value, fn(User $user) => false);

        expect(fn() => new Authorization()->authorize(ReportAbility::View))->toThrow(AuthorizationError::class);
    });

    it('denies a guest when the gate does not allow guest access', function () {
        auth()->logout();
        Gate::define('view-report', fn(User $user) => true);

        expect(fn() => new Authorization()->authorize('view-report'))->toThrow(AuthorizationError::class);
    });
});

describe('Authorization injection', function () {
    it('is hydrated as a value object when #[Authorize] provides it', function () {
        $item = discoverAuthorizationHelper()['composedHelper'];

        expect($item->argCompositions)->toBe(['auth' => Authorization::class])
            ->and($item->containerInjections)->toBe([]);
    });

    it('falls back to the container when no #[Authorize] is present', function () {
        $item = discoverAuthorizationHelper()['injectedHelper'];

        expect($item->containerInjections)->toBe(['auth' => Authorization::class])
            ->and($item->argCompositions)->toBe([]);
    });

    it('exposes no GraphQL arg for either route', function () {
        foreach (['composedHelper', 'injectedHelper'] as $method) {
            expect(discoverAuthorizationHelper()[$method]->createType(app())->args())->toBe([]);
        }
    });

    it('resolves through both routes', function () {
        $items = discoverAuthorizationHelper();

        expect($items['composedHelper']->createType(app())->resolve(null, [], null, null))->toBe('ok')
            ->and($items['injectedHelper']->createType(app())->resolve(null, [], null, null))->toBe('ok');
    });
});

describe('Authorization helper end-to-end', function () {
    it('answers a denied ability with an authorization error', function () {
        $this->actingAs(new User());
        Gate::define('view-report', fn(User $user) => false);

        $this->postJson('/graphql', ['query' => '{ guardedReport }'])
            ->assertOk()
            ->assertJsonPath('data.guardedReport', null)
            ->assertJsonPath('errors.0.message', 'Forbidden')
            ->assertJsonPath('errors.0.extensions.category', 'authorization');
    });

    it('resolves once the ability passes', function () {
        $this->actingAs(new User());
        Gate::define('view-report', fn(User $user) => true);

        $this->postJson('/graphql', ['query' => '{ guardedReport }'])
            ->assertOk()
            ->assertJsonPath('data.guardedReport', 'quarterly');
    });
});
