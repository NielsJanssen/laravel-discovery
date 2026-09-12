<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\Fixtures\Validation\Profile;

describe('caller-supplied rules and messages', function () {
    it('merges caller-supplied rules over the object rules', function () {
        expect(Validator::makeFromObject(new Profile(), ['name' => ['min:100']])->fails())->toBeTrue();
    });

    it('merges caller-supplied messages', function () {
        $validator = Validator::makeFromObject(
            new Profile(),
            ['name' => ['min:100']],
            ['name.min' => 'Name is far too short.'],
        );

        expect($validator->errors()->first('name'))->toBe('Name is far too short.');
    });
});

describe('validateObject', function () {
    it('returns the validated data on success', function () {
        expect(Validator::validateObject(new Profile()))->toBeArray()->toHaveKey('name');
    });

    it('throws a ValidationException on failure', function () {
        expect(fn() => Validator::validateObject(new Profile(), ['name' => ['min:100']]))
            ->toThrow(ValidationException::class);
    });
});
