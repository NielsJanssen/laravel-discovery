<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use NielsJanssen\Laravel\Validation\RuleCompiler;
use Tests\Fixtures\Validation\InferenceForm;

it('does not repeat an inferred rule the property declares itself', function () {
    $rules = app(RuleCompiler::class)->forObject(new InferenceForm())->rules;

    expect($rules['explicitNullable'])->toBe(['string', 'nullable', 'min:2'])
        ->and($rules['explicitString'])->toBe(['string', 'min:2']);
});

it('infers nothing from a union type', function () {
    expect(app(RuleCompiler::class)->forObject(new InferenceForm())->rules['union'])->toBe(['min:2']);
});

it('marks a defaulted parameter sometimes, not nullable', function () {
    $rules = app(RuleCompiler::class)->forMethod(new InferenceForm(), 'optional', [])->rules;

    expect($rules['required'])->toBe(['string', 'min:2'])
        ->and($rules['defaulted'])->toBe(['sometimes', 'string', 'min:2'])
        ->and($rules['nullableDefaulted'])->toBe(['sometimes', 'nullable', 'string', 'min:2']);
});

it('lets an omitted defaulted argument pass, but still validates a given one', function () {
    expect(Validator::makeFromMethod(new InferenceForm(), 'optional', ['required' => 'abc'])->passes())->toBeTrue()
        ->and(Validator::makeFromMethod(new InferenceForm(), 'optional', ['required' => 'abc', 'defaulted' => 'x'])->fails())->toBeTrue();
});

it('lets an explicit type attribute override the inferred one', function () {
    expect(app(RuleCompiler::class)->forObject(new InferenceForm())->rules['overridden'])->toBe(['numeric']);
});
