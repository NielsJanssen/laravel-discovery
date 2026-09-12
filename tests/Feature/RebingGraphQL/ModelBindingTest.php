<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredAction;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\GraphQLDiscovery;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use Tests\Fixtures\RebingGraphQL\ModelBindingQuery;
use Workbench\App\Models\User;

/**
 * @return array<string, DiscoveredAction>
 */
function discoverModelBindings(): array
{
    $discovery = app(GraphQLDiscovery::class);
    $discovery->setItems(new DiscoveryItems());

    $location = new DiscoveryLocation(
        namespace: 'Tests\\Fixtures\\GraphQL',
        path: dirname(__DIR__, 2) . '/Fixtures/RebingGraphQL',
    );

    $discovery->discover($location, new ClassReflector(ModelBindingQuery::class));

    $byMethod = [];

    foreach ($discovery->getItems() as $item) {
        /** @var DiscoveredAction $item */
        $byMethod[$item->method] = $item;
    }

    return $byMethod;
}

describe('model binding discovery', function () {
    it('records a model binding for a model-typed parameter and keeps it out of the regular args', function () {
        $item = discoverModelBindings()['requiredById'];

        expect($item->args)->toBe([])
            ->and($item->modelBindings)->toHaveCount(1);

        $binding = $item->modelBindings[0];

        expect($binding->paramName)->toBe('user')
            ->and($binding->argName)->toBe('id')
            ->and($binding->modelClass)->toBe(User::class)
            ->and($binding->nullable)->toBeFalse();
    });

    it('exposes a non-null ID arg with an auto exists rule on the route key', function () {
        $field = discoverModelBindings()['requiredById']->createType(app());

        $arg = $field->args()['id'];

        expect((string) $arg['type'])->toBe('ID!')
            ->and($arg['rules'])->toHaveCount(1)
            ->and((string) $arg['rules'][0])->toBe('exists:users,id');
    });

    it('treats a model type alone as the trigger, even without an attribute', function () {
        $item = discoverModelBindings()['bareUser'];

        expect($item->modelBindings)->toHaveCount(1)
            ->and($item->modelBindings[0]->argName)->toBe('user')
            ->and($item->modelBindings[0]->nullable)->toBeFalse()
            ->and($item->containerInjections)->toBe([]);
    });

    it('makes a nullable binding an optional ID arg with no exists rule', function () {
        $item = discoverModelBindings()['optionalUser'];

        expect($item->modelBindings[0]->nullable)->toBeTrue();

        $arg = $item->createType(app())->args()['user'];

        expect((string) $arg['type'])->toBe('ID')
            ->and($arg)->not->toHaveKey('rules');
    });

    it('honours #[Arg(type:)] as an explicit type override for the binding arg', function () {
        $arg = discoverModelBindings()['typedId']->createType(app())->args()['id'];

        expect((string) $arg['type'])->toBe('String!');
    });

    it('merges user-supplied #[Arg(rules:)] with the auto exists rule', function () {
        $arg = discoverModelBindings()['extraRules']->createType(app())->args()['id'];

        expect($arg['rules'])->toHaveCount(2)
            ->and((string) $arg['rules'][0])->toBe('exists:users,id')
            ->and($arg['rules'][1])->toBe('integer');
    });
});

describe('model binding resolution', function () {
    beforeEach(function () {
        $this->loadLaravelMigrations();
    });

    it('fetches the model by id and injects it into the resolver', function () {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);

        $field = discoverModelBindings()['requiredById']->createType(app());

        expect($field->resolve(null, ['id' => $user->id], null, null))->toBe('Ada Lovelace');
    });

    it('injects null for a nullable binding when no value is given', function () {
        $field = discoverModelBindings()['optionalUser']->createType(app());

        expect($field->resolve(null, [], null, null))->toBe('none');
    });

    it('injects null for a nullable binding when the model is missing', function () {
        $field = discoverModelBindings()['optionalUser']->createType(app());

        expect($field->resolve(null, ['user' => 999999], null, null))->toBe('none');
    });
});

describe('model binding end-to-end', function () {
    beforeEach(function () {
        $this->loadLaravelMigrations();
    });

    it('resolves the model and returns its typed fields over the GraphQL endpoint', function () {
        $user = User::factory()->create(['name' => 'Edsger Dijkstra', 'email' => 'edsger@example.com']);

        $this->postJson('/graphql', ['query' => "{ user(id: {$user->id}) { id name email } }"])
            ->assertOk()
            ->assertJsonPath('data.user.name', 'Edsger Dijkstra')
            ->assertJsonPath('data.user.email', 'edsger@example.com');
    });

    it('rejects a non-existent id with the auto exists validation rule', function () {
        $this->postJson('/graphql', ['query' => '{ user(id: 999999) { id } }'])
            ->assertOk()
            ->assertJsonPath('data.user', null)
            ->assertJsonPath('errors.0.extensions.validation.id.0', 'The selected id is invalid.');
    });
});

describe('validation attributes on a model-bound parameter', function () {
    it('keys their rules onto the GraphQL arg, not the parameter name', function () {
        $item = discoverModelBindings()['validatedBinding'];

        expect($item->toArgPath('user'))->toBe('id')
            ->and($item->toParameters(['id' => '7']))->toBe(['user' => '7']);

        $rules = $item->createType(app())->getRules();

        expect($rules)->toHaveKey('id')
            ->and($rules)->not->toHaveKey('user')
            ->and($rules['id'])->toContain('numeric');
    });
});
