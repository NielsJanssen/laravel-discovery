<?php

declare(strict_types=1);

use NielsJanssen\Laravel\Validation\RuleCompiler;
use Illuminate\Support\Facades\Validator;
use NielsJanssen\Laravel\Validation\ConditionalString;
use Tests\Fixtures\Validation\AcceptedFieldForm;
use Tests\Fixtures\Validation\TermsForm;

it('builds a conditional "accepted" rule from a closure', function () {
    $rules = app(RuleCompiler::class)->forObject(new TermsForm())->rules;

    expect(end($rules['gdprConsent']))->toBeInstanceOf(ConditionalString::class);
});

it('does not require acceptance when the condition is false', function () {
    $form = new TermsForm();
    $form->isEuResident = false;
    $form->gdprConsent = false;

    expect(Validator::makeFromObject($form)->passes())->toBeTrue();
});

it('requires acceptance when the condition is true', function () {
    $form = new TermsForm();
    $form->isEuResident = true;
    $form->gdprConsent = false;

    expect(Validator::makeFromObject($form)->fails())->toBeTrue();

    $form->gdprConsent = true;
    expect(Validator::makeFromObject($form)->passes())->toBeTrue();
});

it('still supports the classic field/value form for accepted_if', function () {
    $rules = app(RuleCompiler::class)->forObject(new AcceptedFieldForm())->rules;

    expect($rules['terms'])->toBe(['boolean', 'accepted_if:plan,pro']);
});
