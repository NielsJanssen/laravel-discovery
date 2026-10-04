<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\Hydrator;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\HydratorRegistry;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\RuleProvider;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\RuleProviderRegistry;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\GraphQLDiscovery;
use Rebing\GraphQL\Support\Facades\GraphQL;
use RuntimeException;
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

describe('the RuleProvider hook', function () {
    it('lets a third-party provider contribute rules with no change to the package', function () {
        tagArgumentHook(RuleProvider::TAG, [RejectEverythingRules::class]);

        $field = discoveredActions(ValidatedNameFixtureQuery::class)['resolve']->createType(app());

        expect($field->getRules(['name' => 'Niels'])['name'])->toContain('in:impossible');
    });

    it('merges rules from several providers rather than letting one win', function () {
        tagArgumentHook(RuleProvider::TAG, [RejectEverythingRules::class, RequireLongNameRules::class]);

        $rules = discoveredActions(ValidatedNameFixtureQuery::class)['resolve']->createType(app())->getRules(['name' => 'Niels']);

        expect($rules['name'])->toContain('in:impossible');

        expect($rules['name'])->toContain('min:50');
    });

    it('carries a provider message through to the validator', function () {
        tagArgumentHook(RuleProvider::TAG, [RejectEverythingRules::class]);

        $field = discoveredActions(ValidatedNameFixtureQuery::class)['resolve']->createType(app());

        expect($field->validationErrorMessages(['name' => 'Niels']))
            ->toHaveKey('name.in', 'Nothing gets past me.');
    });

    it('keeps #[Arg(rules:)] working, so the hook is purely additive', function () {
        // No providers tagged beyond whatever ships by default.
        $rules = discoveredActions(ValidatedNameFixtureQuery::class)['resolve']->createType(app())->getRules(['name' => 'ab']);

        expect($rules['name'])->toContain('min:3');
    });

    it('validates with the registry bound when the schema is built, not the one bound at boot', function () {
        isolateGraphQL();
        app()->instance(RuleProviderRegistry::class, new RuleProviderRegistry([new RejectEverythingRules()]));
        discoverGraphQL(ValidatedNameFixtureQuery::class)->apply();

        $result = GraphQL::query('{ fixtureValidatedHello(name: "Niels") }');

        expect($result['errors'][0]['extensions']['validation'] ?? null)->toBe(['name' => ['Nothing gets past me.']]);
    });
});

describe('the Hydrator hook', function () {
    it('hydrates a class that does not implement ComposedFromArgs', function () {
        tagArgumentHook(Hydrator::TAG, [PlainCursorHydrator::class]);

        $action = discoveredActions(HydratedCursorQuery::class)['resolve'];

        expect($action->parameters->argCompositions)->toBe(['cursor' => PlainCursor::class]);

        expect($action->parameters->args)->toBeEmpty();

        $resolved = $action->createType(app())->resolve(null, ['cursor' => 'abc'], null, null);

        expect($resolved)->toBe('cursor abc');
    });

    it('leaves a class no hydrator claims as a container injection', function () {
        // PlainCursorHydrator is NOT tagged here, so nothing hydrates PlainCursor.
        app()->forgetInstance(HydratorRegistry::class);
        app()->forgetInstance(GraphQLDiscovery::class);

        $action = discoveredActions(HydratedCursorQuery::class)['resolve'];

        expect($action->parameters->argCompositions)->toBe([]);

        expect($action->parameters->containerInjections)->toBe(['cursor' => PlainCursor::class]);
    });

    it('still hydrates ComposedFromArgs value objects through the built-in hydrator', function () {
        $action = discoveredActions(ValueObjectValidatedQuery::class)['resolve'];

        expect($action->parameters->argCompositions)->toBe(['page' => TestPage::class]);

        expect($action->createType(app())->resolve(null, ['offset' => 7], null, null))->toBe('offset 7');
    });
});

describe('keying and registry edge cases', function () {
    it('keys a hydrated value object rule onto the flat arg it is built from', function () {
        $action = discoveredActions(ValueObjectValidatedQuery::class)['resolve'];

        expect(app(RuleProviderRegistry::class)->rulesFor($action, ['offset' => 1])->rules['offset'])
            ->toContain('min:1');

        expect($action->createType(app())->getRules(['offset' => 0])['offset'])->toContain('min:1');
    });

    it('degrades to nothing when no rules provider is registered', function () {
        $providers = new RuleProviderRegistry([]);
        $set = $providers->rulesFor(discoveredActions(ValidatedNameFixtureQuery::class)['resolve'], []);

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
});
