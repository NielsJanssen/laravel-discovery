<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use NielsJanssen\Laravel\Validation\Rule\Min;
use NielsJanssen\Laravel\Validation\Rule\Rule;
use NielsJanssen\Laravel\Validation\RuleCompiler;
use NielsJanssen\Laravel\Validation\RuleFinder;
use Tests\Fixtures\Validation\DuplicateMessages;
use Tests\Fixtures\Validation\EvenNumber;
use Tests\Fixtures\Validation\MessagedForm;

describe('rule strings', function () {
    it('compiles a named attribute to its rule string', function () {
        $rules = app(RuleCompiler::class)->forObject(new MessagedForm())->rules;

        expect($rules['name'])->toBe(['string', 'min:5']);
        expect($rules['amount'])->toBe(['numeric']);
        expect($rules['title'])->toBe(['string', 'required']);
        expect($rules['years'])->toBe(['integer', 'min:18']);
        expect($rules['plain'])->toBe(['string', 'min:3']);
    });

    it('passes a foreign rule object straight through', function () {
        $rules = app(RuleCompiler::class)->forObject(new MessagedForm())->rules;

        expect($rules['age'][1])->toBeInstanceOf(EvenNumber::class);
    });
});

describe('message keys', function () {
    it('keys every message under the rule Laravel sees', function () {
        expect(app(RuleCompiler::class)->forObject(new MessagedForm())->messages)->toBe([
            'name.min' => 'Give it at least five.',
            'amount.numeric' => 'Numbers only.',
            'title.required' => 'The title is not optional.',
            'years.min' => 'Adults only.',
        ]);
    });

    it('surfaces the custom message on a named attribute failure', function () {
        $form = new MessagedForm();
        $form->name = 'ab';

        expect(Validator::makeFromObject($form)->errors()->first('name'))->toBe('Give it at least five.');
    });

    it('surfaces the custom message on a #[Rule] string failure', function () {
        $form = new MessagedForm();
        $form->years = 12;

        expect(Validator::makeFromObject($form)->errors()->first('years'))->toBe('Adults only.');
    });

    it('leaves the other rules on the member with their default messages', function () {
        $form = new MessagedForm();
        $form->amount = 'abc';
        $form->name = 'ab';

        expect(Validator::makeFromObject($form)->errors()->first('amount'))->toBe('Numbers only.');
        expect(Validator::makeFromObject($form)->errors()->first('name'))->toBe('Give it at least five.');
    });
});

describe('rejected message combinations', function () {
    it('rejects a message on a #[Rule] holding more than one rule', function () {
        expect(fn() => new Rule(['min:2', 'max:4'], message: 'Between two and four, please.'))
            ->toThrow(LogicException::class, 'a message for a single rule only');
    });

    it('accepts a message on a #[Rule] holding exactly one rule', function () {
        expect(new Rule(['min:2'], message: 'At least two.')->messageKey)->toBe('min');
    });

    it('rejects two attributes keying a message under the same rule', function () {
        expect(fn() => app(RuleFinder::class)->find(DuplicateMessages::class))
            ->toThrow(LogicException::class, 'two messages for the "min" rule');
    });

    it('names the member in the duplicate message error', function () {
        expect(fn() => app(RuleFinder::class)->find(DuplicateMessages::class))
            ->toThrow(LogicException::class, DuplicateMessages::class . '::$code');
    });

    it('leaves two attributes of the same rule alone when only one carries a message', function () {
        $rules = app(RuleCompiler::class)->forObject(new class {
            #[Min(2)]
            #[Min(3, message: 'At least three.')]
            public string $code = 'abcd';
        })->rules;

        expect($rules['code'])->toBe(['string', 'min:2', 'min:3']);
    });
});
