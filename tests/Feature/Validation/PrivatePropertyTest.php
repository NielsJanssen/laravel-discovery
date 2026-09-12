<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use NielsJanssen\Laravel\Validation\RuleCompiler;
use Tests\Fixtures\Validation\PrivateForm;

describe('private and promoted properties', function () {
    it('extracts their rules', function () {
        $rules = app(RuleCompiler::class)->forObject(new PrivateForm())->rules;

        expect($rules['code'])->toBe(['string', 'min:3']);
        expect($rules['name'])->toBe(['string', 'min:5']);
        expect($rules['email'])->toBe(['nullable', 'string', 'email']);
    });

    it('reads their values for validation', function () {
        expect(Validator::makeFromObject(new PrivateForm())->passes())->toBeTrue();
    });

    it('fails a private readonly property that breaks its rule', function () {
        expect(Validator::makeFromObject(new PrivateForm(name: 'No'))->fails())->toBeTrue();
    });
});
