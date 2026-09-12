<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum as LaravelEnum;
use NielsJanssen\Laravel\Validation\RuleCompiler;
use Tests\Fixtures\Validation\InferenceForm;
use Tests\Fixtures\Validation\Status;

describe('inferred rules', function () {
    it('does not repeat an inferred rule the property declares itself', function () {
        $rules = app(RuleCompiler::class)->forObject(new InferenceForm())->rules;

        expect($rules['explicitNullable'])->toBe(['string', 'nullable', 'min:2']);
        expect($rules['explicitString'])->toBe(['string', 'min:2']);
    });

    it('infers nothing from a union type', function () {
        expect(app(RuleCompiler::class)->forObject(new InferenceForm())->rules['union'])->toBe(['min:2']);
    });
});

describe('explicit type attributes', function () {
    it('lets an explicit type attribute replace the inferred one', function () {
        expect(app(RuleCompiler::class)->forObject(new InferenceForm())->rules['overridden'])->toBe(['numeric']);
    });

    it('keeps the arguments of an explicit type attribute', function () {
        expect(app(RuleCompiler::class)->forObject(new InferenceForm())->rules['strictFlag'])->toBe(['boolean:strict']);
    });

    it('emits one enum rule for an enum-typed property that names its own', function () {
        $rules = app(RuleCompiler::class)->forObject(new InferenceForm())->rules['status'];

        expect($rules)->toHaveCount(1);
        expect($rules[0])->toBeInstanceOf(LaravelEnum::class);
    });

    it('honours the narrowed cases of the explicit enum rule', function () {
        $form = new InferenceForm();
        $form->status = Status::Closed;

        expect(Validator::makeFromObject($form)->fails())->toBeTrue();
    });
});

describe('method parameters', function () {
    it('marks a defaulted parameter sometimes, not nullable', function () {
        $rules = app(RuleCompiler::class)->forMethod(new InferenceForm(), 'optional', [])->rules;

        expect($rules['required'])->toBe(['string', 'min:2']);
        expect($rules['defaulted'])->toBe(['sometimes', 'string', 'min:2']);
        expect($rules['nullableDefaulted'])->toBe(['sometimes', 'nullable', 'string', 'min:2']);
    });

    it('lets an omitted defaulted argument pass', function () {
        expect(Validator::makeFromMethod(new InferenceForm(), 'optional', ['required' => 'abc'])->passes())->toBeTrue();
    });

    it('still validates a defaulted argument that was given', function () {
        expect(Validator::makeFromMethod(new InferenceForm(), 'optional', ['required' => 'abc', 'defaulted' => 'x'])->fails())->toBeTrue();
    });
});
