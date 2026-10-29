<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\Fixtures\RebingGraphQL\AuthorizedBindingQuery;
use Workbench\App\Models\User;

describe('#[Authorize] discovery on a model-bound parameter', function () {
    it('records the abilities on the binding', function () {
        $binding = discoveredActions(AuthorizedBindingQuery::class)['twiceAuthorized']->parameters->modelBindings[0];

        expect(array_map(fn($a) => $a->ability, $binding->authorizations))->toBe(['view', 'update']);
    });
});

describe('bound model authorization', function () {
    beforeEach(function () {
        $this->loadLaravelMigrations();
    });

    it('denies when the gate refuses the bound model', function () {
        $user = User::factory()->create(['name' => 'Grace Hopper']);
        Gate::define('view', fn(?User $actor, User $subject) => $subject->name === 'Ada Lovelace');

        $field = discoveredActions(AuthorizedBindingQuery::class)['authorizedUser']->createType(app());

        expect($field->authorize(null, ['id' => $user->id], null, null))->toBeFalse();
    });

    it('reports the message of the ability that failed', function () {
        $user = User::factory()->create();
        Gate::define('view', fn() => false);

        $field = discoveredActions(AuthorizedBindingQuery::class)['authorizedWithMessage']->createType(app());

        expect($field->authorize(null, ['id' => $user->id], null, null))->toBeFalse()
            ->and($field->getAuthorizationMessage())->toBe('Not your user');
    });

    it('requires every ability on the parameter to pass', function () {
        $user = User::factory()->create();
        Gate::define('view', fn() => true);
        Gate::define('update', fn() => false);

        $field = discoveredActions(AuthorizedBindingQuery::class)['twiceAuthorized']->createType(app());

        expect($field->authorize(null, ['id' => $user->id], null, null))->toBeFalse();
    });

    it('skips the check for a nullable binding with nothing to authorize', function () {
        Gate::define('view', fn() => false);

        $field = discoveredActions(AuthorizedBindingQuery::class)['authorizedOptionalUser']->createType(app());

        expect($field->authorize(null, [], null, null))->toBeTrue();
    });

    it('skips the check when a nullable binding finds no record', function () {
        Gate::define('view', fn() => false);

        $field = discoveredActions(AuthorizedBindingQuery::class)['authorizedOptionalUser']->createType(app());

        expect($field->authorize(null, ['user' => 999999], null, null))->toBeTrue();
    });

    it('still enforces the ability when a nullable binding finds its record', function () {
        $user = User::factory()->create();
        Gate::define('view', fn(?User $actor, User $subject) => false);

        $field = discoveredActions(AuthorizedBindingQuery::class)['authorizedOptionalUser']->createType(app());

        expect($field->authorize(null, ['user' => $user->id], null, null))->toBeFalse();
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

    it('returns null for a nullable binding whose record is gone, instead of refusing', function () {
        $this->actingAs(User::factory()->create());
        Gate::define('view', fn(User $actor, User $subject) => true);

        $this->postJson('/graphql', ['query' => '{ authorizedOptionalUser(id: 999999) }'])
            ->assertOk()
            ->assertJsonPath('data.authorizedOptionalUser', null)
            ->assertJsonMissingPath('errors');
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

describe('reusing the bound record', function () {
    beforeEach(function () {
        $this->loadLaravelMigrations();
        Gate::define('view', fn(?User $actor, User $subject) => true);
    });

    it('hands the record authorize() found to the resolver without looking it up again', function () {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);
        $field = discoveredActions(AuthorizedBindingQuery::class)['authorizedUser']->createType(app());

        $field->authorize(null, ['id' => $user->id], null, null);
        DB::enableQueryLog();

        expect($field->resolve(null, ['id' => $user->id], null, null))->toBe('Ada Lovelace')
            ->and(DB::getQueryLog())->toBe([]);
    });

    it('looks a value up that authorize() did not see', function () {
        $ada = User::factory()->create(['name' => 'Ada Lovelace']);
        $grace = User::factory()->create(['name' => 'Grace Hopper']);
        $field = discoveredActions(AuthorizedBindingQuery::class)['authorizedUser']->createType(app());

        $field->authorize(null, ['id' => $ada->id], null, null);

        expect($field->resolve(null, ['id' => $grace->id], null, null))->toBe('Grace Hopper');
    });

    it('forgets the record once the call has resolved', function () {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);
        $field = discoveredActions(AuthorizedBindingQuery::class)['authorizedUser']->createType(app());

        $field->authorize(null, ['id' => $user->id], null, null);
        $field->resolve(null, ['id' => $user->id], null, null);
        $user->update(['name' => 'Grace Hopper']);

        expect($field->resolve(null, ['id' => $user->id], null, null))->toBe('Grace Hopper');
    });

    it('drops the exists rule once authorize() found the record', function () {
        $user = User::factory()->create();
        $field = discoveredActions(AuthorizedBindingQuery::class)['authorizedUser']->createType(app());

        $before = array_map(strval(...), $field->getRules(['id' => $user->id])['id']);
        $field->authorize(null, ['id' => $user->id], null, null);

        expect($before)->toBe(['exists:users,id'])
            ->and($field->getRules(['id' => $user->id])['id'])->toBe([]);
    });

    it('looks the bound record up once over the GraphQL endpoint', function () {
        $user = User::factory()->create(['name' => 'Edsger Dijkstra']);
        $this->actingAs(User::factory()->create());
        DB::enableQueryLog();

        $this->postJson('/graphql', ['query' => "{ authorizedUser(id: {$user->id}) }"])
            ->assertOk()
            ->assertJsonPath('data.authorizedUser', 'Edsger Dijkstra');

        $lookups = array_filter(array_column(DB::getQueryLog(), 'query'), fn(string $query) => str_contains($query, '"users"'));

        expect($lookups)->toHaveCount(1);
    });
});
