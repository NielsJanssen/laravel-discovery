<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use NielsJanssen\Laravel\Validation\Nesting;
use NielsJanssen\Laravel\Validation\RuleCompiler;
use NielsJanssen\Laravel\Validation\RuleFinder;
use Tests\Fixtures\Validation\Country;
use Tests\Fixtures\Validation\CustomNesting;

it('lets a user-defined attribute drive nesting, found by interface', function () {
    $form = new CustomNesting();
    $form->visited = [new Country('NL'), new Country('TOO LONG')];

    expect(app(RuleFinder::class)->find(CustomNesting::class)->members['visited']->nesting)
        ->toBe(Nesting::Each);

    $validator = Validator::makeFromObject($form);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->keys())->toBe(['visited.1.code']);
});

it('enforces a user-defined attribute allowed classes', function () {
    $form = new CustomNesting();
    $form->visited = ['not a country'];

    expect(Validator::makeFromObject($form)->errors()->first('visited.0'))
        ->toContain('must be Country, string given');
});

it('lets a user-defined attribute supply its own message', function () {
    $form = new CustomNesting();
    $form->postcode = 'nope';

    expect(app(RuleCompiler::class)->forObject($form)->messages)
        ->toBe(['postcode.regex' => 'That is not a valid postcode.'])
        ->and(Validator::makeFromObject($form)->errors()->first('postcode'))
        ->toBe('That is not a valid postcode.');
});
