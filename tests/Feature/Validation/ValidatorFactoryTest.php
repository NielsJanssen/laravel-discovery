<?php

declare(strict_types=1);

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Factory;
use NielsJanssen\Laravel\Validation\ValidatorFactory;

function adoptingFactory(callable $registerOnOriginal): ValidatorFactory
{
    $original = new Factory(app(Translator::class), app());

    $registerOnOriginal($original);

    return new ValidatorFactory(app(Translator::class), app())->adopting($original);
}

describe('adopting a replaced factory', function () {
    it('runs an extension the replaced factory carried', function () {
        $factory = adoptingFactory(static function (Factory $original): void {
            $original->extend('even', static fn(string $attribute, mixed $value): bool => $value % 2 === 0);
        });

        expect($factory->make(['n' => 4], ['n' => 'even'])->passes())->toBeTrue();
        expect($factory->make(['n' => 3], ['n' => 'even'])->fails())->toBeTrue();
    });

    it('carries over the replacer for that extension', function () {
        $factory = adoptingFactory(static function (Factory $original): void {
            $original->extend('even', static fn(string $attribute, mixed $value): bool => $value % 2 === 0);
            $original->replacer('even', static fn(): string => 'Only even numbers.');
        });

        expect($factory->make(['n' => 3], ['n' => 'even'])->errors()->first('n'))->toBe('Only even numbers.');
    });

    it('carries over an implicit extension, so it runs on a missing value', function () {
        $factory = adoptingFactory(static function (Factory $original): void {
            $original->extendImplicit('present_always', static fn(string $attribute, mixed $value): bool => $value !== null);
        });

        expect($factory->make([], ['n' => 'present_always'])->fails())->toBeTrue();
    });

    it('carries over a dependent extension', function () {
        $factory = adoptingFactory(static function (Factory $original): void {
            $original->extendDependent('matches_sibling', static fn(string $attribute, mixed $value, array $parameters, $validator): bool => $value === $validator->getData()[$parameters[0]]);
        });

        expect($factory->make(['a' => 1, 'b' => 1], ['a' => 'matches_sibling:b'])->passes())->toBeTrue();
    });

    it('carries over a fallback message', function () {
        $factory = adoptingFactory(static function (Factory $original): void {
            $original->extend('even', static fn(string $attribute, mixed $value): bool => $value % 2 === 0, 'Odd is no good.');
        });

        expect($factory->make(['n' => 3], ['n' => 'even'])->errors()->first('n'))->toBe('Odd is no good.');
    });
});

describe('the bound validator', function () {
    it('replaces the container binding with our factory', function () {
        expect(app('validator'))->toBeInstanceOf(ValidatorFactory::class);
    });

    it('keeps an extension registered through the facade working', function () {
        Validator::extend('even', static fn(string $attribute, mixed $value): bool => $value % 2 === 0);

        expect(Validator::make(['n' => 4], ['n' => 'even'])->passes())->toBeTrue();
    });
});
