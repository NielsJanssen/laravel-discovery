<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\AnyOf;
use Illuminate\Validation\Rules\Can;
use Illuminate\Validation\Rules\Email;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\ImageFile;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\Password;
use NielsJanssen\Laravel\Validation\RuleCompiler;
use Tests\Fixtures\Validation\ObjectRuleForm;

function objectRules(string $member): array
{
    return array_values(array_filter(
        app(RuleCompiler::class)->forObject(new ObjectRuleForm())->rules[$member],
        static fn(mixed $rule): bool => $rule !== 'nullable',
    ));
}

describe('#[Enum]', function () {
    it('builds Laravel\'s enum rule object', function () {
        expect(objectRules('narrowed')[0])->toBeInstanceOf(Enum::class);
        expect(objectRules('excepted')[0])->toBeInstanceOf(Enum::class);
    });

    it('accepts a case the `only` list names', function () {
        expect(Validator::makeFromObject(new ObjectRuleForm())->passes())->toBeTrue();
    });

    it('rejects a case outside the `only` list', function () {
        $form = new ObjectRuleForm();
        $form->narrowed = 'closed';

        expect(Validator::makeFromObject($form)->fails())->toBeTrue();
    });

    it('rejects a case the `except` list names', function () {
        $form = new ObjectRuleForm();
        $form->excepted = 'closed';

        expect(Validator::makeFromObject($form)->fails())->toBeTrue();
    });

    it('keys its message under the Laravel rule class', function () {
        expect(app(RuleCompiler::class)->forObject(new ObjectRuleForm())->messages)
            ->toHaveKey('narrowed.' . Enum::class, 'Not a status we accept.');
    });

    it('surfaces that message end to end', function () {
        $form = new ObjectRuleForm();
        $form->narrowed = 'closed';

        expect(Validator::makeFromObject($form)->errors()->first('narrowed'))->toBe('Not a status we accept.');
    });
});

describe('#[Email]', function () {
    it('stays a plain rule string when no option is set', function () {
        expect(objectRules('bareEmail'))->toBe(['email']);
    });

    it('becomes a rule object as soon as an option is set', function () {
        expect(objectRules('strictEmail')[0])->toBeInstanceOf(Email::class);
    });

    it('rejects a malformed address either way', function () {
        $form = new ObjectRuleForm();
        $form->bareEmail = 'nope';
        $form->strictEmail = 'nope';

        expect(Validator::makeFromObject($form)->errors()->keys())->toBe(['bareEmail', 'strictEmail']);
    });
});

describe('#[Password]', function () {
    it('builds Laravel\'s password rule object', function () {
        expect(objectRules('password')[0])->toBeInstanceOf(Password::class);
    });

    it('rejects a password that misses a requirement', function () {
        $form = new ObjectRuleForm();
        $form->password = 'short';

        expect(Validator::makeFromObject($form)->fails())->toBeTrue();
    });

    it('keys its message under the Laravel rule class', function () {
        expect(app(RuleCompiler::class)->forObject(new ObjectRuleForm())->messages)
            ->toHaveKey('password.' . Password::class, 'Pick a stronger password.');
    });

    it('surfaces that message end to end', function () {
        $form = new ObjectRuleForm();
        $form->password = 'short';

        expect(Validator::makeFromObject($form)->errors()->first('password'))->toBe('Pick a stronger password.');
    });
});

describe('#[Can] and #[AnyOf]', function () {
    it('builds a Can rule object', function () {
        expect(objectRules('post')[0])->toBeInstanceOf(Can::class);
    });

    it('builds an AnyOf rule object', function () {
        expect(objectRules('anyOf')[0])->toBeInstanceOf(AnyOf::class);
    });

    it('accepts a value matching one of the alternatives', function () {
        $form = new ObjectRuleForm();
        $form->anyOf = 42;

        expect(Validator::makeFromObject($form)->errors()->keys())->not->toContain('anyOf');
    });

    it('rejects a value matching none of the alternatives', function () {
        $form = new ObjectRuleForm();
        $form->anyOf = 'ab';

        expect(Validator::makeFromObject($form)->fails())->toBeTrue();
    });
});

describe('#[ImageFile]', function () {
    it('builds Laravel\'s image file rule object', function () {
        expect(objectRules('avatar')[0])->toBeInstanceOf(ImageFile::class);
        expect(objectRules('vector')[0])->toBeInstanceOf(ImageFile::class);
    });
});

describe('#[In]', function () {
    it('quotes its values', function () {
        expect(objectRules('country')[0])->toBeInstanceOf(In::class);
        expect((string) objectRules('country')[0])->toBe('in:"nl","be"');
    });

    it('keys its message under the plain rule Laravel parses it back to', function () {
        expect(app(RuleCompiler::class)->forObject(new ObjectRuleForm())->messages)
            ->toHaveKey('country.in', 'Pick a country we ship to.');
    });

    it('surfaces that message end to end', function () {
        $form = new ObjectRuleForm();
        $form->country = 'de';

        expect(Validator::makeFromObject($form)->errors()->first('country'))->toBe('Pick a country we ship to.');
    });
});
