<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use NielsJanssen\Laravel\Validation\RuleCompiler;
use NielsJanssen\Laravel\Validation\RuleFinder;
use Tempest\Reflection\ClassReflector;
use Tests\Fixtures\Validation\OrderAction;
use Tests\Fixtures\Validation\Profile;

describe('method arguments', function () {
    it('extracts rules and data from method arguments', function () {
        $set = app(RuleCompiler::class)->forMethod(new OrderAction(), 'place', ['sku' => 'A1', 'quantity' => 5]);

        expect($set->rules['sku'])->toBe(['string', 'min:1']);
        expect($set->rules['quantity'])->toBe(['integer', 'between:1,999']);
        expect($set->data)->toBe(['sku' => 'A1', 'quantity' => 5]);
    });

    it('ignores method parameters without an attribute', function () {
        $set = app(RuleCompiler::class)->forMethod(new OrderAction(), 'place', ['sku' => 'A1', 'quantity' => 5, 'note' => 'x']);

        expect($set->rules)->not->toHaveKey('note');
        expect($set->data)->not->toHaveKey('note');
    });

    it('validates method arguments through the produced rule set', function () {
        $set = app(RuleCompiler::class)->forMethod(new OrderAction(), 'place', ['sku' => 'A1', 'quantity' => 0]);

        expect(Validator::make($set->data, $set->rules)->fails())->toBeTrue();
    });

    it('fails an out-of-range argument through the facade', function () {
        expect(Validator::makeFromMethod(new OrderAction(), 'place', ['sku' => 'A1', 'quantity' => 0])->fails())->toBeTrue();
    });

    it('passes a valid set of arguments through the facade', function () {
        expect(Validator::makeFromMethod(new OrderAction(), 'place', ['sku' => 'A1', 'quantity' => 5])->passes())->toBeTrue();
    });

    it('keys a method rule set by class and method name', function () {
        expect(app(RuleFinder::class)->find(new ClassReflector(OrderAction::class)->getMethod('place'))->name)
            ->toBe(OrderAction::class . '::place');
    });
});

describe('the cached plan', function () {
    it('reads values fresh on each call', function () {
        $profile = new Profile();

        $first = app(RuleCompiler::class)->forObject($profile);
        $profile->name = 'A completely different name';
        $second = app(RuleCompiler::class)->forObject($profile);

        expect($first->data['name'])->toBe('Niels Janssen');
        expect($second->data['name'])->toBe('A completely different name');
    });

    it('produces the same rules across calls', function () {
        $profile = new Profile();

        $first = app(RuleCompiler::class)->forObject($profile);
        $profile->name = 'A completely different name';
        $second = app(RuleCompiler::class)->forObject($profile);

        expect(array_keys($second->rules))->toBe(array_keys($first->rules));
        expect($second->rules['name'])->toBe($first->rules['name']);
    });
});
