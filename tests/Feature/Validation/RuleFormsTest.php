<?php

declare(strict_types=1);

use NielsJanssen\Laravel\Validation\RuleCompiler;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\In;
use Tests\Fixtures\Validation\RuleFormsFixture;

it('spreads the array form of #[Rule]', function () {
    expect(app(RuleCompiler::class)->forObject(new RuleFormsFixture())->rules['arrayForm'])->toBe(['string', 'min:10', 'max:20']);
});

it('resolves a closure factory returning a rule object', function () {
    $rules = app(RuleCompiler::class)->forObject(new RuleFormsFixture())->rules['factoryObject'];

    expect($rules[0])->toBe('string')
        ->and($rules[1])->toBeInstanceOf(In::class);
});

it('resolves a closure factory returning an array of rules', function () {
    expect(app(RuleCompiler::class)->forObject(new RuleFormsFixture())->rules['factoryArray'])->toBe(['string', 'min:2', 'max:4']);
});

it('hands the validation context to a factory', function () {
    expect(app(RuleCompiler::class)->forObject(new RuleFormsFixture())->rules['contextAware'])->toBe(['string', 'in:contextAware']);
});

it('resolves a user-defined attribute with no registration', function () {
    expect(app(RuleCompiler::class)->forObject(new RuleFormsFixture())->rules['captcha'])->toBe(['string', 'string', 'size:6', 'alpha_num']);
});

it('validates the fixture end to end', function () {
    expect(Validator::makeFromObject(new RuleFormsFixture())->passes())->toBeTrue();

    $invalid = new RuleFormsFixture();
    $invalid->factoryObject = 'de';          // not in nl,be

    expect(Validator::makeFromObject($invalid)->fails())->toBeTrue();

    $short = new RuleFormsFixture();
    $short->arrayForm = 'tiny';              // min:10

    expect(Validator::makeFromObject($short)->fails())->toBeTrue();
});
