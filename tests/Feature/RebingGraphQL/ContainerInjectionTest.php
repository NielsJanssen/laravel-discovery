<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use Tests\Fixtures\RebingGraphQL\ContainerInjectionQuery;
use Tests\Fixtures\RebingGraphQL\ContainerService;
use Workbench\App\Models\User;

describe('container injection of resolve parameters', function () {
    it('records class-typed unattributed parameters as container injections during discovery', function () {
        $item = discoveredActions(ContainerInjectionQuery::class)['resolve'];

        expect($item->args)->toHaveCount(1)
            ->and($item->args[0]->paramName)->toBe('name')
            ->and($item->injections)->toBe([
                'root' => 'root',
                'context' => 'context',
                'info' => 'info',
            ])
            ->and($item->containerInjections)->toBe([
                'service' => ContainerService::class,
            ]);
    });

    it('resolves the container-injected parameter at resolve time alongside args, root, context and ResolveInfo', function () {
        $field = discoveredActions(ContainerInjectionQuery::class)['resolve']->createType(app());

        expect($field->resolve('root-value', ['name' => 'Niels'], 'context-value', null))
            ->toBe('Niels!');
    });
});

describe('Laravel ContextualAttribute injection via $container->call()', function () {
    it('injects the authenticated user into a #[CurrentUser] parameter end-to-end', function () {
        $user = new User(['email' => 'niels@example.com']);

        $this->actingAs($user);

        $this->postJson('/graphql', ['query' => '{ currentUser }'])
            ->assertOk()
            ->assertJsonPath('data.currentUser', config('app.name') . ':niels@example.com');
    });

    it('falls back to null on #[CurrentUser] when no user is authenticated and still injects #[Config] values', function () {
        auth()->logout();
        config()->set('app.name', 'TestApp');

        $this->postJson('/graphql', ['query' => '{ currentUser }'])
            ->assertOk()
            ->assertJsonPath('data.currentUser', 'TestApp:guest');
    });
});
