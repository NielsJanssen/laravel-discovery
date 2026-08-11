<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\Hydrator;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\HydratorRegistry;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\RuleProviderRegistry;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\RuleProvider;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredAction;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\GraphQLDiscovery;
use RuntimeException;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use Tests\Fixtures\RebingGraphQL\HydratedCursorQuery;
use Tests\Fixtures\RebingGraphQL\PlainCursor;
use Tests\Fixtures\RebingGraphQL\PlainCursorHydrator;
use Tests\Fixtures\RebingGraphQL\RejectEverythingRules;
use Tests\Fixtures\RebingGraphQL\RequireLongNameRules;
use Tests\Fixtures\RebingGraphQL\TestPage;
use Tests\Fixtures\RebingGraphQL\ValidatedNameFixtureQuery;
use Tests\Fixtures\RebingGraphQL\ValueObjectValidatedQuery;

/**
 * Register implementations of a hook, discarding the registry so the new tags take effect. The
 * registries are singletons, so a test that tags late has to forget them.
 *
 * @param  list<class-string>  $implementations
 */
function tagArgumentHook(string $tag, array $implementations): void
{
    app()->tag($implementations, $tag);
    app()->forgetInstance(RuleProviderRegistry::class);
    app()->forgetInstance(HydratorRegistry::class);
    app()->forgetInstance(GraphQLDiscovery::class);
}

function discoveredActionFor(string $class): DiscoveredAction
{
    $discovery = app(GraphQLDiscovery::class);
    $discovery->setItems(new DiscoveryItems());

    $discovery->discover(
        new DiscoveryLocation(
            namespace: 'Tests\\Fixtures\\RebingGraphQL',
            path: dirname(__DIR__, 2) . '/Fixtures/RebingGraphQL',
        ),
        new ClassReflector($class),
    );

    $items = iterator_to_array($discovery->getItems());

    return $items[0];
}

describe('the RuleProvider hook', function () {
    it('lets a third-party provider contribute rules with no change to the package', function () {
        tagArgumentHook(RuleProvider::TAG, [RejectEverythingRules::class]);

        $field = discoveredActionFor(ValidatedNameFixtureQuery::class)->createType(app());

        expect($field->getRules(['name' => 'Niels'])['name'])->toContain('in:impossible');
    });

    it('merges rules from several providers rather than letting one win', function () {
        tagArgumentHook(RuleProvider::TAG, [RejectEverythingRules::class, RequireLongNameRules::class]);

        $rules = discoveredActionFor(ValidatedNameFixtureQuery::class)->createType(app())->getRules(['name' => 'Niels']);

        expect($rules['name'])->toContain('in:impossible')
            ->and($rules['name'])->toContain('min:50');
    });

    it('carries a provider message through to the validator', function () {
        tagArgumentHook(RuleProvider::TAG, [RejectEverythingRules::class]);

        $field = discoveredActionFor(ValidatedNameFixtureQuery::class)->createType(app());

        expect($field->validationErrorMessages(['name' => 'Niels']))
            ->toHaveKey('name.in', 'Nothing gets past me.');
    });

    it('keeps #[Arg(rules:)] working, so the hook is purely additive', function () {
        // No providers tagged beyond whatever ships by default.
        $rules = discoveredActionFor(ValidatedNameFixtureQuery::class)->createType(app())->getRules(['name' => 'ab']);

        expect($rules['name'])->toContain('min:3');
    });
});

describe('the Hydrator hook', function () {
    it('hydrates a class that does not implement ComposedFromArgs', function () {
        tagArgumentHook(Hydrator::TAG, [PlainCursorHydrator::class]);

        $action = discoveredActionFor(HydratedCursorQuery::class);

        expect($action->argCompositions)->toBe(['cursor' => PlainCursor::class])
            ->and($action->args)->toBeEmpty();

        $resolved = $action->createType(app())->resolve(null, ['cursor' => 'abc'], null, null);

        expect($resolved)->toBe('cursor abc');
    });

    it('leaves a class no hydrator claims as a container injection', function () {
        // PlainCursorHydrator is NOT tagged here, so nothing hydrates PlainCursor.
        app()->forgetInstance(HydratorRegistry::class);
        app()->forgetInstance(GraphQLDiscovery::class);

        $action = discoveredActionFor(HydratedCursorQuery::class);

        expect($action->argCompositions)->toBe([])
            ->and($action->containerInjections)->toBe(['cursor' => PlainCursor::class]);
    });

    it('still hydrates ComposedFromArgs value objects through the built-in hydrator', function () {
        $action = discoveredActionFor(ValueObjectValidatedQuery::class);

        expect($action->argCompositions)->toBe(['page' => TestPage::class])
            ->and($action->createType(app())->resolve(null, ['offset' => 7], null, null))->toBe('offset 7');
    });
});

it('keys a hydrated value object rule onto the flat arg it is built from', function () {
    $action = discoveredActionFor(ValueObjectValidatedQuery::class);

    expect(app(RuleProviderRegistry::class)->rulesFor($action, ['offset' => 1])->rules['offset'])
        ->toContain('min:1');

    expect($action->createType(app())->getRules(['offset' => 0])['offset'])->toContain('min:1');
});

it('degrades to nothing when no rules provider is registered', function () {
    $providers = new RuleProviderRegistry([]);
    $set = $providers->rulesFor(discoveredActionFor(ValidatedNameFixtureQuery::class), []);

    expect($set->isEmpty())->toBeTrue();
});

it('names the class when nothing can hydrate it', function () {
    expect(fn() => new HydratorRegistry([])->hydrate(PlainCursor::class, []))
        ->toThrow(RuntimeException::class, 'No Hydrator handles');
});

it('rejects a binding tagged as a hook it does not implement', function () {
    tagArgumentHook(RuleProvider::TAG, [PlainCursorHydrator::class]);

    expect(fn() => app(RuleProviderRegistry::class))
        ->toThrow(RuntimeException::class, 'does not implement');
});
