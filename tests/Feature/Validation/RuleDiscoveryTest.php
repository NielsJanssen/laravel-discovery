<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
use Tests\Fixtures\Validation\Database\IgnoreOwnId;
use Tests\Fixtures\Validation\MultiMethodAction;
use Tests\Fixtures\Validation\Order;
use Tests\Fixtures\Validation\OrderAction;
use Tests\Fixtures\Validation\PartialForm;
use Tests\Fixtures\Validation\Profile;
use Tests\Fixtures\Validation\Unannotated;
use Workbench\App\Models\User;

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

describe('what gets discovered', function () {
    it('discovers classes with annotated properties', function () {
        expect(cacheOf(discoverRules(PartialForm::class)))->toHaveKey(PartialForm::class);
    });

    it('discovers every annotated method, not just the first', function () {
        expect(cacheOf(discoverRules(MultiMethodAction::class)))->toHaveKeys([
            MultiMethodAction::class . '::first',
            MultiMethodAction::class . '::second',
        ]);
    });

    it('skips classes and methods with no validation attributes', function () {
        expect(cacheOf(discoverRules(Unannotated::class)))->toBe([]);
    });

    it('discovers a class and its methods side by side', function () {
        $cache = cacheOf(discoverRules(OrderAction::class));

        expect($cache)->toHaveKey(OrderAction::class . '::place');
        expect($cache)->not->toHaveKey(OrderAction::class);
    });
});

describe('the discovery cache', function () {
    it('survives the discovery cache: closures never reach it', function () {
        $discovery = discoverRules(Profile::class, ConditionalForm::class, Order::class, IgnoreOwnId::class);

        expect(serialize($discovery->getItems()))->toBeString();
    });

    it('reads back the same rules live reflection builds', function () {
        $compiler = new RuleCompiler(app(RuleFinder::class)->withCache(restoredCache(
            Profile::class,
            ConditionalForm::class,
            Order::class,
            IgnoreOwnId::class,
        )));

        expect($compiler->forObject(new Profile())->rules['name'])->toBe(['string', 'min:5', 'max:255']);
        expect($compiler->forObject(new Order())->rules)->toHaveKey('customer.country.code');
    });

    it('resolves a closure-backed conditional rule through AttributeRef', function () {
        $compiler = new RuleCompiler(app(RuleFinder::class)->withCache(restoredCache(ConditionalForm::class)));

        $form = new ConditionalForm();
        $form->subscribe = true;

        expect($compiler->forObject($form)->rules['email'])->toHaveCount(3);
    });

    it('resolves a closure-backed #[Unique] through AttributeRef', function () {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });

        User::unguarded(static function (): void {
            User::create(['id' => 5, 'name' => 'Ada', 'email' => 'ada@example.com']);
        });

        $compiler = new RuleCompiler(app(RuleFinder::class)->withCache(restoredCache(IgnoreOwnId::class)));

        $form = new IgnoreOwnId();
        $form->id = 5;
        $form->email = 'ada@example.com';

        expect((string) $compiler->forObject($form)->rules['email'][1])->toBe('unique:users,email,"5",id');

        Schema::dropIfExists('users');
    });
});

describe('serving the cache', function () {
    it('serves cached rules through the container after apply()', function () {
        discoverRules(PartialForm::class)->apply();

        expect(app(RuleFinder::class)->find(PartialForm::class)->members)->toHaveKey('name');
    });

    it('falls back to live reflection for a class that was never discovered', function () {
        $compiler = new RuleCompiler(app(RuleFinder::class)->withCache([]));

        expect($compiler->forObject(new PartialForm())->rules['name'])->toBe(['string', 'min:5']);
    });

    it('validates a class that was never discovered', function () {
        expect(Validator::makeFromObject(new PartialForm())->passes())->toBeTrue();
    });

    it('is registered through the discovery boot', function () {
        expect(config('discovery.discovery_classes'))->toContain(RuleDiscovery::class);
    });
});

/**
 * Discover the given classes, serialize the items the way the discovery cache does and read the
 * rule sets back out again.
 *
 * @return array<string, RuleSet>
 */
function restoredCache(string ...$classes): array
{
    /** @var DiscoveryItems $restored */
    $restored = unserialize(serialize(discoverRules(...$classes)->getItems()));

    $cache = [];

    foreach ($restored as $item) {
        $cache[$item->rules->name] = $item->rules;
    }

    return $cache;
}
