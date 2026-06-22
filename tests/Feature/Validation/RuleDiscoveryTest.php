<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use NielsJanssen\Laravel\Validation\DiscoveredRules;
use NielsJanssen\Laravel\Validation\RuleCompiler;
use NielsJanssen\Laravel\Validation\RuleDiscovery;
use NielsJanssen\Laravel\Validation\RuleFinder;
use NielsJanssen\Laravel\Validation\RuleSet;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use Tests\Fixtures\Validation\ConditionalForm;
use Tests\Fixtures\Validation\MultiMethodAction;
use Tests\Fixtures\Validation\Order;
use Tests\Fixtures\Validation\OrderAction;
use Tests\Fixtures\Validation\PartialForm;
use Tests\Fixtures\Validation\Profile;
use Tests\Fixtures\Validation\Unannotated;

function discoverRules(string ...$classes): RuleDiscovery
{
    $discovery = new RuleDiscovery(app(), app(RuleFinder::class));
    $discovery->setItems(new DiscoveryItems());

    $location = new DiscoveryLocation(
        namespace: 'Tests\\Fixtures\\Validation',
        path: dirname(__DIR__, 2) . '/Fixtures/Validation',
    );

    foreach ($classes as $class) {
        $discovery->discover($location, new ClassReflector($class));
    }

    return $discovery;
}

/**
 * @return array<string, RuleSet>
 */
function cacheOf(RuleDiscovery $discovery): array
{
    $cache = [];

    foreach ($discovery->getItems() as $item) {
        expect($item)->toBeInstanceOf(DiscoveredRules::class);

        $cache[$item->rules->name] = $item->rules;
    }

    return $cache;
}

it('discovers classes with annotated properties', function () {
    expect(cacheOf(discoverRules(PartialForm::class)))->toHaveKey(PartialForm::class);
});

it('discovers every annotated method, not just the first', function () {
    $cache = cacheOf(discoverRules(MultiMethodAction::class));

    expect($cache)->toHaveKeys([
        MultiMethodAction::class . '::first',
        MultiMethodAction::class . '::second',
    ]);
});

it('skips classes and methods with no validation attributes', function () {
    expect(cacheOf(discoverRules(Unannotated::class)))->toBe([]);
});

it('discovers a class and its methods side by side', function () {
    $cache = cacheOf(discoverRules(OrderAction::class));

    expect($cache)->toHaveKey(OrderAction::class . '::place')
        ->and($cache)->not->toHaveKey(OrderAction::class);   // no annotated properties
});

it('survives the discovery cache: closures never reach it', function () {
    // Profile carries #[Rule(closure)] and a #[Rule(object)]; ConditionalForm carries
    // closure-backed conditional rules. Both must serialize.
    $discovery = discoverRules(Profile::class, ConditionalForm::class, Order::class);

    $serialized = serialize($discovery->getItems());

    expect($serialized)->toBeString();

    /** @var DiscoveryItems $restored */
    $restored = unserialize($serialized);
    $cache = [];

    foreach ($restored as $item) {
        $cache[$item->rules->name] = $item->rules;
    }

    $compiler = new RuleCompiler(app(RuleFinder::class)->withCache($cache));

    // Rules read back out of the cache match the ones built by live reflection.
    expect($compiler->forObject(new Profile())->rules['name'])->toBe(['string', 'min:5', 'max:255'])
        ->and($compiler->forObject(new Order())->rules)->toHaveKey('customer.country.code');

    // And the closure-backed rules still resolve, through AttributeRef.
    $form = new ConditionalForm();
    $form->subscribe = true;

    expect($compiler->forObject($form)->rules['email'])->toHaveCount(3);
});

it('serves cached rules through the container after apply()', function () {
    discoverRules(PartialForm::class)->apply();

    expect(app(RuleFinder::class)->find(PartialForm::class)->members)->toHaveKey('name');
});

it('falls back to live reflection for a class that was never discovered', function () {
    // Nothing discovered at all, yet validation still works.
    $compiler = new RuleCompiler(app(RuleFinder::class)->withCache([]));

    expect($compiler->forObject(new PartialForm())->rules['name'])->toBe(['string', 'min:5'])
        ->and(Validator::makeFromObject(new PartialForm())->passes())->toBeTrue();
});

it('is registered through the discovery boot', function () {
    expect(config('discovery.discovery_classes'))->toContain(RuleDiscovery::class);
});
