<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use ReflectionProperty;
use Tests\Fixtures\RebingGraphQL\ContainerInjectionQuery;
use Tests\Fixtures\RebingGraphQL\ContainerService;
use Workbench\App\Models\User;

/** Reads the memoised decision, so a test fails when the fallback is taken for a fully mapped method. */
function callsDirectly(object $field): bool
{
    return (bool) new ReflectionProperty($field, 'callsDirectly')->getValue($field);
}

describe('container injection of resolve parameters', function () {
    it('records class-typed unattributed parameters as container injections during discovery', function () {
        $item = discoveredActions(ContainerInjectionQuery::class)['resolve'];

        expect($item->parameters->args)->toHaveCount(1)
            ->and($item->parameters->args[0]->paramName)->toBe('name')
            ->and($item->parameters->injections)->toBe([
                'root' => 'root',
                'context' => 'context',
                'info' => 'info',
            ])
            ->and($item->parameters->containerInjections)->toBe([
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

describe('calling the action method', function () {
    it('calls a method straight when resolve() maps every parameter', function () {
        $field = discoveredActions(ContainerInjectionQuery::class)['mapped']->createType(app());

        expect($field->resolve('parent', ['name' => 'Ada'], null, null))->toBe('Ada:parent')
            ->and(callsDirectly($field))->toBeTrue();
    });

    it('leaves a parameter resolve() does not map to the container', function () {
        $actions = discoveredActions(ContainerInjectionQuery::class);
        $injected = $actions['resolve']->createType(app());
        $signedIn = $actions['signedIn']->createType(app());

        $this->actingAs(new User(['email' => 'ada@example.com']));

        expect($injected->resolve(null, ['name' => 'Niels'], null, null))->toBe('Niels!')
            ->and($signedIn->resolve(null, ['greeting' => 'Hi'], null, null))->toBe('Hi, ada@example.com')
            ->and(callsDirectly($injected))->toBeFalse()
            ->and(callsDirectly($signedIn))->toBeFalse();
    });

    it('honours a method binding', function () {
        $field = discoveredActions(ContainerInjectionQuery::class)['mapped']->createType(app());

        app()->bindMethod(ContainerInjectionQuery::class . '@mapped', static fn(ContainerInjectionQuery $query): string => 'bound');

        expect($field->resolve(null, ['name' => 'Ada'], null, null))->toBe('bound');
    });

    it('honours a method binding on the class the container resolves the host to', function () {
        $field = discoveredActions(ContainerInjectionQuery::class)['mapped']->createType(app());
        $subclass = new class extends ContainerInjectionQuery {};

        app()->instance(ContainerInjectionQuery::class, $subclass);
        app()->bindMethod($subclass::class . '@mapped', static fn(ContainerInjectionQuery $query): string => 'bound on the subclass');

        expect($field->resolve(null, ['name' => 'Ada'], null, null))->toBe('bound on the subclass');
    });

    it('passes a variadic parameter its value as the container does', function () {
        $field = discoveredActions(ContainerInjectionQuery::class)['joined']->createType(app());

        expect($field->resolve(null, ['parts' => 'a'], null, null))->toBe('0=a')
            ->and(callsDirectly($field))->toBeFalse();
    });
});
