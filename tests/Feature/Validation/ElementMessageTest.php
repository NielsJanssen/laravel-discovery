<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use NielsJanssen\Laravel\Validation\RuleCompiler;
use Tests\Fixtures\Validation\Country;
use Tests\Fixtures\Validation\MessagedElements;

it('keys an #[Each] element message per index', function () {
    $form = new MessagedElements();
    $form->recipients = ['a@example.com', 'nope', 'also-bad'];

    expect(app(RuleCompiler::class)->forObject($form)->messages)->toBe([
        'recipients.0.email' => 'That is not an email.',
        'recipients.1.email' => 'That is not an email.',
        'recipients.2.email' => 'That is not an email.',
    ]);
});

it('surfaces the element message against the offending index only', function () {
    $form = new MessagedElements();
    $form->recipients = ['a@example.com', 'nope'];

    $errors = Validator::makeFromObject($form)->errors();

    expect($errors->keys())->toBe(['recipients.1'])
        ->and($errors->first('recipients.1'))->toBe('That is not an email.');
});

it('keys a wrapped named attribute inside #[Each] under its rule suffix', function () {
    $form = new MessagedElements();
    $form->aliases = ['ab'];

    expect(app(RuleCompiler::class)->forObject($form)->messages)
        ->toHaveKey('aliases.0.min', 'Too short an alias.');

    expect(Validator::makeFromObject($form)->errors()->first('aliases.0'))->toBe('Too short an alias.');
});

it('emits element messages alongside #[ListOf] nesting', function () {
    $form = new MessagedElements();
    $form->countries = [new Country('NL')];

    $compiled = app(RuleCompiler::class)->forObject($form);

    expect($compiled->messages)->toHaveKey('countries.0.required', 'A country is required here.')
        ->and($compiled->rules)->toHaveKeys(['countries.0', 'countries.0.code']);
});

it('emits nothing for an empty iterable', function () {
    expect(app(RuleCompiler::class)->forObject(new MessagedElements())->messages)->toBe([]);
});
