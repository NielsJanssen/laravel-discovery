<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use NielsJanssen\Laravel\Validation\Nesting;
use NielsJanssen\Laravel\Validation\RuleCompiler;
use NielsJanssen\Laravel\Validation\RuleFinder;
use Tests\Fixtures\Validation\Country;
use Tests\Fixtures\Validation\CustomNesting;

describe('a user-defined nesting attribute', function () {
    it('drives nesting, found by interface', function () {
        expect(app(RuleFinder::class)->find(CustomNesting::class)->members['visited']->nesting)
            ->toBe(Nesting::Each);
    });

    it('fails the offending element at its own path', function () {
        $form = new CustomNesting();
        $form->visited = [new Country('NL'), new Country('TOO LONG')];

        $validator = Validator::makeFromObject($form);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->keys())->toBe(['visited.1.code']);
    });

    it('enforces the classes it allows', function () {
        $form = new CustomNesting();
        $form->visited = ['not a country'];

        expect(Validator::makeFromObject($form)->errors()->first('visited.0'))
            ->toContain('must be Country, string given');
    });
});

describe('a user-defined message', function () {
    it('keys the message under the rule it belongs to', function () {
        $form = new CustomNesting();
        $form->postcode = 'nope';

        expect(app(RuleCompiler::class)->forObject($form)->messages)
            ->toBe(['postcode.regex' => 'That is not a valid postcode.']);
    });

    it('surfaces that message end to end', function () {
        $form = new CustomNesting();
        $form->postcode = 'nope';

        expect(Validator::makeFromObject($form)->errors()->first('postcode'))
            ->toBe('That is not a valid postcode.');
    });
});
