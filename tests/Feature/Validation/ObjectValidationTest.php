<?php

declare(strict_types=1);

use NielsJanssen\Laravel\Validation\RuleCompiler;
use Illuminate\Support\Facades\Validator;
use NielsJanssen\Laravel\Validation\ValidatorFactory;
use Tests\Fixtures\Validation\Address;
use Tests\Fixtures\Validation\Profile;

it('rebinds the validator factory and exposes makeFromObject through the facade', function () {
    expect(app('validator'))->toBeInstanceOf(ValidatorFactory::class);
    expect(Validator::makeFromObject(new Profile()))->toBeInstanceOf(Illuminate\Validation\Validator::class);
});

it('infers base rules from the property type and merges attribute rules', function () {
    $rules = app(RuleCompiler::class)->forObject(new Profile())->rules;

    expect($rules['name'])->toBe(['string', 'min:5', 'max:255'])
        ->and($rules['email'])->toBe(['nullable', 'string', 'email'])     // ?string → nullable + string
        ->and($rules['score'][0])->toBe('integer');                       // non-null int → no `required`, just type
});

it('does not add required to non-nullable properties', function () {
    $rules = app(RuleCompiler::class)->forObject(new Profile())->rules;

    expect($rules['name'])->not->toContain('required');
});

it('passes a fully valid object', function () {
    expect(Validator::makeFromObject(new Profile())->passes())->toBeTrue();
});

it('fails when a named-rule attribute is violated', function () {
    $profile = new Profile();
    $profile->name = 'No';                       // min:5

    expect(Validator::makeFromObject($profile)->fails())->toBeTrue();
});

it('enforces a generic #[Rule] backed by a ValidationRule object', function () {
    $profile = new Profile();
    $profile->age = 31;                          // odd → EvenNumber fails

    expect(Validator::makeFromObject($profile)->fails())->toBeTrue();
});

it('enforces a generic #[Rule] backed by a closure', function () {
    $profile = new Profile();
    $profile->score = -1;                        // closure: must be positive

    $validator = Validator::makeFromObject($profile);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('score'))->toContain('must be positive');
});

it('surfaces a custom message from #[Rule(message:)]', function () {
    $profile = new Profile();
    $profile->age = 16;                          // even, but min:18 → custom message

    $validator = Validator::makeFromObject($profile);

    expect($validator->errors()->first('age'))->toBe('Too young.');
});

it('reads a computed (hooked) property through its getter', function () {
    $profile = new Profile();
    $profile->email = null;                      // hasEmail get-hook → false → #[Accepted] fails

    expect(Validator::makeFromObject($profile)->fails())->toBeTrue();
});

it('validates nested objects with dotted-path rules', function () {
    $rules = app(RuleCompiler::class)->forObject(new Profile())->rules;

    expect($rules)->toHaveKey('address.street')
        ->and($rules['address.street'])->toBe(['string', 'min:5']);

    $profile = new Profile();
    $profile->address = new Address('road');     // min:5 on street

    expect(Validator::makeFromObject($profile)->fails())->toBeTrue();
});
