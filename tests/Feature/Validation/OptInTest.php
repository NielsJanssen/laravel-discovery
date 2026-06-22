<?php

declare(strict_types=1);

use NielsJanssen\Laravel\Validation\RuleCompiler;
use Illuminate\Support\Facades\Validator;
use Tests\Fixtures\Validation\PartialForm;

it('only generates rules for properties that carry an attribute', function () {
    $rules = app(RuleCompiler::class)->forObject(new PartialForm())->rules;

    expect($rules)->toHaveKey('name')
        ->and($rules)->not->toHaveKey('note')
        ->and($rules)->not->toHaveKey('internal');
});

it('does not validate unannotated properties even when their value would fail', function () {
    // `note` is 'x' which would fail min:5, but it has no attribute → ignored.
    expect(Validator::makeFromObject(new PartialForm())->passes())->toBeTrue();
});
