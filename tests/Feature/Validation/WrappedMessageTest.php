<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use NielsJanssen\Laravel\Validation\RuleCompiler;
use Tests\Fixtures\Validation\EvenNumber;
use Tests\Fixtures\Validation\WrappedMessageForm;
use NielsJanssen\Laravel\Validation\Rule\Rule;

it('unwraps a named attribute wrapped in #[Rule] into its rule string', function () {
    $rules = app(RuleCompiler::class)->forObject(new WrappedMessageForm())->rules;

    expect($rules['name'])->toBe(['string', 'min:5'])
        ->and($rules['code'])->toBe(['string', 'min:2', 'max:4'])
        ->and($rules['amount'])->toBe(['string', 'numeric'])
        ->and($rules['plain'])->toBe(['string', 'min:3']);
});

it('keys the message under the wrapped rule suffix', function () {
    expect(app(RuleCompiler::class)->forObject(new WrappedMessageForm())->messages)->toBe([
        'name.min' => 'Give it at least five.',
        'code.min' => 'Between two and four, please.',
        'amount.numeric' => 'Numbers only.',
    ]);
});

it('surfaces the custom message on a named attribute failure', function () {
    $form = new WrappedMessageForm();
    $form->name = 'ab';

    expect(Validator::makeFromObject($form)->errors()->first('name'))->toBe('Give it at least five.');
});

it('leaves the rest of the wrapped rules with their default messages', function () {
    $form = new WrappedMessageForm();
    $form->code = 'abcdefg';   // fails max:4, whose message was keyed to min

    expect(Validator::makeFromObject($form)->errors()->first('code'))
        ->toContain('must not be greater than 4');
});

it('still passes a foreign rule object straight through', function () {
    // EvenNumber implements Laravel's ValidationRule, not ours, so it must not be unwrapped.
    $rules = app(RuleCompiler::class)->forObject(new class {
        #[Rule(new EvenNumber())]
        public int $age = 4;
    })->rules;

    expect($rules['age'][1])->toBeInstanceOf(EvenNumber::class);
});
