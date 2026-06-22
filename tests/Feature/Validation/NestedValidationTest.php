<?php

declare(strict_types=1);

use NielsJanssen\Laravel\Validation\RuleCompiler;
use Illuminate\Support\Facades\Validator;
use Tests\Fixtures\Validation\Country;
use Tests\Fixtures\Validation\Customer;
use Tests\Fixtures\Validation\Order;
use Tests\Fixtures\Validation\RequiredNested;
use NielsJanssen\Laravel\Validation\RuleFinder;

it('recurses two levels deep behind #[Valid]', function () {
    $rules = app(RuleCompiler::class)->forObject(new Order())->rules;

    expect($rules)->toHaveKeys(['reference', 'customer.name', 'customer.country.code'])
        ->and($rules['customer.country.code'])->toBe(['string', 'size:2']);
});

it('does not recurse into a class-typed property without #[Valid]', function () {
    $rules = app(RuleCompiler::class)->forObject(new Order())->rules;

    expect($rules)->not->toHaveKey('customer.billingCountry')
        ->and($rules)->not->toHaveKey('customer.billingCountry.code');
});

it('nests the data alongside the rules', function () {
    $data = app(RuleCompiler::class)->forObject(new Order())->data;

    expect($data['customer']['country']['code'])->toBe('NL');
});

it('fails on a violation two levels down', function () {
    $valid = new Order();

    expect(Validator::makeFromObject($valid)->passes())->toBeTrue();

    $invalid = new Order(new Customer(new Country('TOO LONG')));

    expect(Validator::makeFromObject($invalid)->fails())->toBeTrue()
        ->and(Validator::makeFromObject($invalid)->errors()->keys())->toContain('customer.country.code');
});

it('caches each nested class under its own name', function () {
    app(RuleCompiler::class)->forObject(new Order());

    $finder = app(RuleFinder::class);

    expect($finder->find(Country::class)->name)->toBe(Country::class)
        ->and($finder->find(Customer::class)->members)->toHaveKeys(['name', 'country']);
});

it('composes #[Valid] with the property own rules', function () {
    $form = new RequiredNested();

    // Absent: only the property's own rules apply, nothing to recurse into.
    expect(app(RuleCompiler::class)->forObject($form)->rules)->toBe(['country' => ['nullable', 'required']])
        ->and(Validator::makeFromObject($form)->fails())->toBeTrue();

    $form->country = new Country();

    expect(app(RuleCompiler::class)->forObject($form)->rules)->toHaveKeys(['country', 'country.code'])
        ->and(Validator::makeFromObject($form)->passes())->toBeTrue();
});
