<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\Fixtures\Validation\Profile;

it('merges caller-supplied rules over the object rules', function () {
    $validator = Validator::makeFromObject(new Profile(), ['name' => ['min:100']]);

    expect($validator->fails())->toBeTrue();
});

it('merges caller-supplied messages', function () {
    $validator = Validator::makeFromObject(
        new Profile(),
        ['name' => ['min:100']],
        ['name.min' => 'Name is far too short.'],
    );

    expect($validator->errors()->first('name'))->toBe('Name is far too short.');
});

it('validateObject returns the validated data on success', function () {
    $data = Validator::validateObject(new Profile());

    expect($data)->toBeArray()->toHaveKey('name');
});

it('validateObject throws a ValidationException on failure', function () {
    expect(fn() => Validator::validateObject(new Profile(), ['name' => ['min:100']]))
        ->toThrow(ValidationException::class);
});
