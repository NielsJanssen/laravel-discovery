<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\RequiredIf as LaravelRequiredIf;
use NielsJanssen\Laravel\Validation\RuleCompiler;
use Tests\Fixtures\Validation\ConditionalForm;
use Tests\Fixtures\Validation\StringConditionalForm;

describe('the closure form', function () {
    it('builds a Laravel conditional rule object', function () {
        $rules = app(RuleCompiler::class)->forObject(new ConditionalForm())->rules;

        expect($rules['email'])->toHaveCount(3);
        expect(end($rules['email']))->toBeInstanceOf(LaravelRequiredIf::class);
    });

    it('leaves the field optional when the condition is false', function () {
        $form = new ConditionalForm();
        $form->subscribe = false;
        $form->email = null;

        expect(Validator::makeFromObject($form)->passes())->toBeTrue();
    });

    it('requires the field when the condition is true', function () {
        $form = new ConditionalForm();
        $form->subscribe = true;
        $form->email = null;

        expect(Validator::makeFromObject($form)->fails())->toBeTrue();
    });

    it('prohibits a field while its condition holds', function () {
        $form = new ConditionalForm();
        $form->subscribe = false;
        $form->topic = 'deals';

        expect(Validator::makeFromObject($form)->fails())->toBeTrue();
    });

    it('allows that field once its condition no longer holds', function () {
        $form = new ConditionalForm();
        $form->subscribe = true;
        $form->topic = 'deals';
        $form->email = 'me@example.com';

        expect(Validator::makeFromObject($form)->passes())->toBeTrue();
    });
});

describe('the field and value form', function () {
    it('writes the rule Laravel names', function () {
        $rules = app(RuleCompiler::class)->forObject(new StringConditionalForm())->rules;

        expect($rules['reason'])->toBe(['nullable', 'string', 'required_unless:subscribe,1']);
    });
});
