<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use NielsJanssen\Laravel\Validation\RuleCompiler;
use NielsJanssen\Laravel\Validation\RuleFinder;
use Tests\Fixtures\Validation\Country;
use Tests\Fixtures\Validation\Customer;
use Tests\Fixtures\Validation\Order;
use Tests\Fixtures\Validation\RequiredNested;

describe('#[Valid] recursion', function () {
    it('recurses two levels deep', function () {
        $rules = app(RuleCompiler::class)->forObject(new Order())->rules;

        expect($rules)->toHaveKeys(['reference', 'customer.name', 'customer.country.code']);
        expect($rules['customer.country.code'])->toBe(['string', 'size:2']);
    });

    it('does not recurse into a class-typed property without #[Valid]', function () {
        $rules = app(RuleCompiler::class)->forObject(new Order())->rules;

        expect($rules)->not->toHaveKey('customer.billingCountry');
        expect($rules)->not->toHaveKey('customer.billingCountry.code');
    });

    it('nests the data alongside the rules', function () {
        expect(app(RuleCompiler::class)->forObject(new Order())->data['customer']['country']['code'])->toBe('NL');
    });

    it('passes a valid graph', function () {
        expect(Validator::makeFromObject(new Order())->passes())->toBeTrue();
    });

    it('fails on a violation two levels down', function () {
        $invalid = new Order(new Customer(new Country('TOO LONG')));

        $validator = Validator::makeFromObject($invalid);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->keys())->toContain('customer.country.code');
    });

    it('caches each nested class under its own name', function () {
        app(RuleCompiler::class)->forObject(new Order());

        expect(app(RuleFinder::class)->find(Country::class)->name)->toBe(Country::class);
        expect(app(RuleFinder::class)->find(Customer::class)->members)->toHaveKeys(['name', 'country']);
    });
});

describe('#[Valid] alongside the property own rules', function () {
    it('applies only the property own rules while the value is absent', function () {
        $form = new RequiredNested();

        expect(app(RuleCompiler::class)->forObject($form)->rules)->toBe(['country' => ['nullable', 'required']]);
        expect(Validator::makeFromObject($form)->fails())->toBeTrue();
    });

    it('recurses once the value is there', function () {
        $form = new RequiredNested();
        $form->country = new Country();

        expect(app(RuleCompiler::class)->forObject($form)->rules)->toHaveKeys(['country', 'country.code']);
        expect(Validator::makeFromObject($form)->passes())->toBeTrue();
    });
});
