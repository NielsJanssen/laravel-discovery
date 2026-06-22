<?php

declare(strict_types=1);

use NielsJanssen\Laravel\Validation\RuleCompiler;
use Illuminate\Support\Facades\Validator;
use Tests\Fixtures\Validation\PrivateForm;

it('extracts rules from private and promoted properties', function () {
    $rules = app(RuleCompiler::class)->forObject(new PrivateForm())->rules;

    expect($rules['code'])->toBe(['string', 'min:3'])
        ->and($rules['name'])->toBe(['string', 'min:5'])
        ->and($rules['email'])->toBe(['nullable', 'string', 'email']);
});

it('reads private property values for validation', function () {
    expect(Validator::makeFromObject(new PrivateForm())->passes())->toBeTrue();

    $invalid = new PrivateForm(name: 'No');     // min:5 on a private readonly property

    expect(Validator::makeFromObject($invalid)->fails())->toBeTrue();
});
