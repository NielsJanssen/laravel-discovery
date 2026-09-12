<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use NielsJanssen\Laravel\Validation\ConditionalString;
use NielsJanssen\Laravel\Validation\RuleCompiler;
use Tests\Fixtures\Validation\AcceptedFieldForm;
use Tests\Fixtures\Validation\TermsForm;

describe('the closure form', function () {
    it('builds a conditional "accepted" rule', function () {
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
    });

    it('passes once the field is accepted', function () {
        $form = new TermsForm();
        $form->isEuResident = true;
        $form->gdprConsent = true;

        expect(Validator::makeFromObject($form)->passes())->toBeTrue();
    });
});

describe('the field and value form', function () {
    it('writes accepted_if as a rule string', function () {
        expect(app(RuleCompiler::class)->forObject(new AcceptedFieldForm())->rules['terms'])
            ->toBe(['boolean', 'accepted_if:plan,pro']);
    });
});
