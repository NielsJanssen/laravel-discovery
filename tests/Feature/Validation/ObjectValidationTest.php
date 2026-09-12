<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use NielsJanssen\Laravel\Validation\RuleCompiler;
use NielsJanssen\Laravel\Validation\ValidatorFactory;
use Tests\Fixtures\Validation\Address;
use Tests\Fixtures\Validation\Profile;

describe('the facade', function () {
    it('rebinds the validator factory', function () {
        expect(app('validator'))->toBeInstanceOf(ValidatorFactory::class);
    });

    it('exposes makeFromObject', function () {
        expect(Validator::makeFromObject(new Profile()))->toBeInstanceOf(Illuminate\Validation\Validator::class);
    });
});

describe('rules from a property', function () {
    it('infers base rules from the property type and merges attribute rules', function () {
        $rules = app(RuleCompiler::class)->forObject(new Profile())->rules;

        expect($rules['name'])->toBe(['string', 'min:5', 'max:255']);
        expect($rules['email'])->toBe(['nullable', 'string', 'email']);
        expect($rules['score'][0])->toBe('integer');
    });

    it('does not add required to non-nullable properties', function () {
        expect(app(RuleCompiler::class)->forObject(new Profile())->rules['name'])->not->toContain('required');
    });
});

describe('validating an object', function () {
    it('passes a fully valid object', function () {
        expect(Validator::makeFromObject(new Profile())->passes())->toBeTrue();
    });

    it('fails when a named-rule attribute is violated', function () {
        $profile = new Profile();
        $profile->name = 'No';

        expect(Validator::makeFromObject($profile)->fails())->toBeTrue();
    });

    it('enforces a generic #[Rule] backed by a ValidationRule object', function () {
        $profile = new Profile();
        $profile->age = 31;

        expect(Validator::makeFromObject($profile)->fails())->toBeTrue();
    });

    it('enforces a generic #[Rule] backed by a closure', function () {
        $profile = new Profile();
        $profile->score = -1;

        $validator = Validator::makeFromObject($profile);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('score'))->toContain('must be positive');
    });

    it('surfaces a custom message from #[Rule(message:)]', function () {
        $profile = new Profile();
        $profile->age = 16;

        expect(Validator::makeFromObject($profile)->errors()->first('age'))->toBe('Too young.');
    });

    it('reads a computed (hooked) property through its getter', function () {
        $profile = new Profile();
        $profile->email = null;

        expect(Validator::makeFromObject($profile)->fails())->toBeTrue();
    });
});

describe('nested objects', function () {
    it('validates them with dotted-path rules', function () {
        $rules = app(RuleCompiler::class)->forObject(new Profile())->rules;

        expect($rules)->toHaveKey('address.street');
        expect($rules['address.street'])->toBe(['string', 'min:5']);
    });

    it('fails on a violation inside the nested object', function () {
        $profile = new Profile();
        $profile->address = new Address('road');

        expect(Validator::makeFromObject($profile)->fails())->toBeTrue();
    });
});
