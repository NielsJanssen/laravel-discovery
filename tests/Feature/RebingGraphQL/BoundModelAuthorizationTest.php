<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use Illuminate\Support\Facades\Gate;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredAction;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\GraphQLDiscovery;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use Tests\Fixtures\RebingGraphQL\AuthorizedBindingQuery;
use Tests\Fixtures\RebingGraphQL\CanOnBoundModelQuery;
use Tests\Fixtures\RebingGraphQL\GateOnParameterQuery;
use Tests\Fixtures\RebingGraphQL\ParameterAuthorizeWithoutAbilityQuery;
use Tests\Fixtures\RebingGraphQL\ParameterAuthorizeWithoutModelQuery;
use Workbench\App\Models\User;

/**
 * @param  class-string  $fixture
 * @return array<string, DiscoveredAction>
 */
function discoverAuthorizedBindings(string $fixture = AuthorizedBindingQuery::class): array
{
    $discovery = app(GraphQLDiscovery::class);
    $discovery->setItems(new DiscoveryItems());

    $location = new DiscoveryLocation(
        namespace: 'Tests\\Fixtures\\GraphQL',
        path: dirname(__DIR__, 2) . '/Fixtures/RebingGraphQL',
    );

    $discovery->discover($location, new ClassReflector($fixture));

    $byMethod = [];

    foreach ($discovery->getItems() as $item) {
        /** @var DiscoveredAction $item */
        $byMethod[$item->method] = $item;
    }

    return $byMethod;
}

describe('#[Authorize] discovery on a model-bound parameter', function () {
    it('records the abilities on the binding', function () {
        $binding = discoverAuthorizedBindings()['twiceAuthorized']->modelBindings[0];

        expect(array_map(fn($a) => $a->ability, $binding->authorizations))->toBe(['view', 'update']);
    });

    it('rejects #[Authorize] on a parameter that binds no model', function () {
        expect(fn() => discoverAuthorizedBindings(ParameterAuthorizeWithoutModelQuery::class))
            ->toThrow(LogicException::class, 'only applies to a model-bound parameter');
    });

    it('rejects a parameter #[Authorize] with no ability', function () {
        expect(fn() => discoverAuthorizedBindings(ParameterAuthorizeWithoutAbilityQuery::class))
            ->toThrow(LogicException::class, 'needs an ability');
    });

    it('rejects #[Authorize(gate:)] on a parameter, since a gate class only sees raw args', function () {
        expect(fn() => discoverAuthorizedBindings(GateOnParameterQuery::class))
            ->toThrow(LogicException::class, 'receives the raw args');
    });
});

describe('#[Can] on a model-bound parameter', function () {
    it('is refused at discovery, because it would authorize the id instead of the record', function () {
        expect(fn() => discoverAuthorizedBindings(CanOnBoundModelQuery::class))
            ->toThrow(LogicException::class, 'would authorize the raw id, not the User it binds');
    });
});

describe('bound model authorization', function () {
    beforeEach(function () {
        $this->loadLaravelMigrations();
    });

    it('passes when the gate allows the bound model', function () {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);
        Gate::define('view', fn(?User $actor, User $subject) => $subject->name === 'Ada Lovelace');

        $field = discoverAuthorizedBindings()['authorizedUser']->createType(app());

        expect($field->authorize(null, ['id' => $user->id], null, null))->toBeTrue();
    });

    it('denies when the gate refuses the bound model, defaulting the message to Forbidden', function () {
        $user = User::factory()->create(['name' => 'Grace Hopper']);
        Gate::define('view', fn(?User $actor, User $subject) => $subject->name === 'Ada Lovelace');

        $field = discoverAuthorizedBindings()['authorizedUser']->createType(app());

        expect($field->authorize(null, ['id' => $user->id], null, null))->toBeFalse()
            ->and($field->getAuthorizationMessage())->toBe('Forbidden');
    });

    it('reports the message of the ability that failed', function () {
        $user = User::factory()->create();
        Gate::define('view', fn() => false);

        $field = discoverAuthorizedBindings()['authorizedWithMessage']->createType(app());

        expect($field->authorize(null, ['id' => $user->id], null, null))->toBeFalse()
            ->and($field->getAuthorizationMessage())->toBe('Not your user');
    });

    it('requires every ability on the parameter to pass', function () {
        $user = User::factory()->create();
        Gate::define('view', fn() => true);
        Gate::define('update', fn() => false);

        $field = discoverAuthorizedBindings()['twiceAuthorized']->createType(app());

        expect($field->authorize(null, ['id' => $user->id], null, null))->toBeFalse();
    });

    it('denies a record that does not exist, so a refusal never confirms one does', function () {
        Gate::define('view', fn() => true);

        $field = discoverAuthorizedBindings()['authorizedUser']->createType(app());

        expect($field->authorize(null, ['id' => 999999], null, null))->toBeFalse();
    });

    it('skips the check for a nullable binding with nothing to authorize', function () {
        Gate::define('view', fn() => false);

        $field = discoverAuthorizedBindings()['authorizedOptionalUser']->createType(app());

        expect($field->authorize(null, [], null, null))->toBeTrue();
    });
});

describe('bound model authorization end-to-end', function () {
    beforeEach(function () {
        $this->loadLaravelMigrations();
    });

    it('answers an unauthorized request with an authorization error, before validation runs', function () {
        $user = User::factory()->create();
        $this->actingAs(User::factory()->create());
        Gate::define('view', fn(User $actor, User $subject) => false);

        $this->postJson('/graphql', ['query' => "{ authorizedUser(id: {$user->id}) }"])
            ->assertOk()
            ->assertJsonPath('data.authorizedUser', null)
            ->assertJsonPath('errors.0.message', 'Forbidden')
            ->assertJsonPath('errors.0.extensions.category', 'authorization');
    });

    it('answers a non-existent id the same way, rather than with the exists rule', function () {
        $this->actingAs(User::factory()->create());
        Gate::define('view', fn(User $actor, User $subject) => true);

        $this->postJson('/graphql', ['query' => '{ authorizedUser(id: 999999) }'])
            ->assertOk()
            ->assertJsonPath('errors.0.message', 'Forbidden')
            ->assertJsonPath('errors.0.extensions.category', 'authorization');
    });

    it('resolves normally once the ability passes', function () {
        $user = User::factory()->create(['name' => 'Edsger Dijkstra']);
        $this->actingAs(User::factory()->create());
        Gate::define('view', fn(User $actor, User $subject) => true);

        $this->postJson('/graphql', ['query' => "{ authorizedUser(id: {$user->id}) }"])
            ->assertOk()
            ->assertJsonPath('data.authorizedUser', 'Edsger Dijkstra');
    });
});
