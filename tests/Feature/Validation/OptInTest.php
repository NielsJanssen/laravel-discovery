<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use NielsJanssen\Laravel\Validation\RuleCompiler;
use Tests\Fixtures\Validation\PartialForm;

describe('opting in per property', function () {
    it('only generates rules for properties that carry an attribute', function () {
        $rules = app(RuleCompiler::class)->forObject(new PartialForm())->rules;

        expect($rules)->toHaveKey('name');
        expect($rules)->not->toHaveKey('note');
        expect($rules)->not->toHaveKey('internal');
    });

    it('does not validate an unannotated property whose value would fail', function () {
        expect(Validator::makeFromObject(new PartialForm())->passes())->toBeTrue();
    });
});
