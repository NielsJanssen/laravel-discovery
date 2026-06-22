<?php

declare(strict_types=1);

use NielsJanssen\Laravel\Validation\RuleCompiler;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\RequiredIf as LaravelRequiredIf;
use Tests\Fixtures\Validation\ConditionalForm;
use Tests\Fixtures\Validation\StringConditionalForm;

it('builds a Laravel conditional rule object when given a closure', function () {
    $rules = app(RuleCompiler::class)->forObject(new ConditionalForm())->rules;

    expect($rules['email'])->toHaveCount(3)                    // nullable, string, <RequiredIf object>
        ->and(end($rules['email']))->toBeInstanceOf(LaravelRequiredIf::class);
});

it('passes the object to the closure: not required when the condition is false', function () {
    $form = new ConditionalForm();
    $form->subscribe = false;
    $form->email = null;

    expect(Validator::makeFromObject($form)->passes())->toBeTrue();
});

it('passes the object to the closure: required when the condition is true', function () {
    $form = new ConditionalForm();
    $form->subscribe = true;
    $form->email = null;

    expect(Validator::makeFromObject($form)->fails())->toBeTrue();
});

it('enforces a prohibited_if closure against the object', function () {
    $form = new ConditionalForm();
    $form->subscribe = false;       // prohibited condition is true...
    $form->topic = 'deals';         // ...and the field is present → fails

    expect(Validator::makeFromObject($form)->fails())->toBeTrue();

    $form->subscribe = true;        // condition now false → topic allowed
    $form->email = 'me@example.com'; // email becomes required once subscribed
    expect(Validator::makeFromObject($form)->passes())->toBeTrue();
});

it('still supports the classic field/value string form', function () {
    $rules = app(RuleCompiler::class)->forObject(new StringConditionalForm())->rules;

    expect($rules['reason'])->toBe(['nullable', 'string', 'required_unless:subscribe,1']);
});
