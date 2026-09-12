<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\In;
use NielsJanssen\Laravel\Validation\RuleCompiler;
use Tests\Fixtures\Validation\RuleFormsFixture;

describe('#[Rule] forms', function () {
    it('spreads the array form', function () {
        expect(app(RuleCompiler::class)->forObject(new RuleFormsFixture())->rules['arrayForm'])
            ->toBe(['string', 'min:10', 'max:20']);
    });

    it('resolves a closure factory returning a rule object', function () {
        $rules = app(RuleCompiler::class)->forObject(new RuleFormsFixture())->rules['factoryObject'];

        expect($rules[0])->toBe('string');
        expect($rules[1])->toBeInstanceOf(In::class);
    });

    it('resolves a closure factory returning an array of rules', function () {
        expect(app(RuleCompiler::class)->forObject(new RuleFormsFixture())->rules['factoryArray'])
            ->toBe(['string', 'min:2', 'max:4']);
    });

    it('hands the validation context to a factory', function () {
        expect(app(RuleCompiler::class)->forObject(new RuleFormsFixture())->rules['contextAware'])
            ->toBe(['string', 'in:contextAware']);
    });
});

describe('user-defined attributes', function () {
    it('resolves a user-defined attribute with no registration', function () {
        // A foreign attribute is not a TypeRule, so the inferred `string` stands next to the
        // `string` the attribute emits itself; Laravel treats the repeat as one rule.
        expect(app(RuleCompiler::class)->forObject(new RuleFormsFixture())->rules['captcha'])
            ->toBe(['string', 'string', 'size:6', 'alpha_num']);
    });
});

describe('end to end', function () {
    it('passes the fixture as it stands', function () {
        expect(Validator::makeFromObject(new RuleFormsFixture())->passes())->toBeTrue();
    });

    it('fails a value outside the factory rule object', function () {
        $invalid = new RuleFormsFixture();
        $invalid->factoryObject = 'de';

        expect(Validator::makeFromObject($invalid)->fails())->toBeTrue();
    });

    it('fails a value below the array form minimum', function () {
        $short = new RuleFormsFixture();
        $short->arrayForm = 'tiny';

        expect(Validator::makeFromObject($short)->fails())->toBeTrue();
    });
});
