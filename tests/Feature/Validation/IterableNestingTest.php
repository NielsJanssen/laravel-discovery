<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use NielsJanssen\Laravel\Validation\Nesting;
use NielsJanssen\Laravel\Validation\Rule\Each;
use NielsJanssen\Laravel\Validation\Rule\ListOf;
use NielsJanssen\Laravel\Validation\RuleCompiler;
use NielsJanssen\Laravel\Validation\RuleFinder;
use Tests\Fixtures\Validation\AnyPayable;
use Tests\Fixtures\Validation\Basket;
use Tests\Fixtures\Validation\BothNestingAttributes;
use Tests\Fixtures\Validation\Cash;
use Tests\Fixtures\Validation\ConditionalForm;
use Tests\Fixtures\Validation\Country;
use Tests\Fixtures\Validation\Customer;
use Tests\Fixtures\Validation\Invoice;
use Tests\Fixtures\Validation\ListOfOnObject;
use Tests\Fixtures\Validation\Narrowed;
use Tests\Fixtures\Validation\ValidOnIterable;

describe('planning', function () {
    it('plans #[Each] as Each, without sniffing the type', function () {
        $basket = app(RuleFinder::class)->find(Basket::class)->members;

        expect($basket['shipTo']->nesting)->toBe(Nesting::Each);
        expect($basket['customers']->nesting)->toBe(Nesting::Each);
        expect($basket['recipients']->nesting)->toBe(Nesting::Each);
    });

    it('plans #[Valid] as Value', function () {
        expect(app(RuleFinder::class)->find(Customer::class)->members['country']->nesting)->toBe(Nesting::Value);
    });

    it('leaves an unmarked array alone', function () {
        expect(app(RuleCompiler::class)->forObject(new Basket())->rules)->not->toHaveKey('untouched');
    });
});

describe('one rule set per element', function () {
    it('keys the element rules by index', function () {
        $basket = new Basket();
        $basket->shipTo = [new Country('NL'), new Country('BE')];

        $rules = app(RuleCompiler::class)->forObject($basket)->rules;

        expect($rules)->toHaveKeys(['shipTo', 'shipTo.0.code', 'shipTo.1.code']);
        expect($rules['shipTo'])->toBe(['array']);
        expect($rules['shipTo.0.code'])->toBe(['string', 'size:2']);
    });

    it('fails on the offending item and names it in the error key', function () {
        $basket = new Basket();
        $basket->shipTo = [new Country('NL'), new Country('TOO LONG'), new Country('DE')];

        $validator = Validator::makeFromObject($basket);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->keys())->toBe(['shipTo.1.code']);
    });

    it('preserves string keys', function () {
        $basket = new Basket();
        $basket->byRegion = ['north' => new Country('NL'), 'south' => new Country('BE')];

        expect(app(RuleCompiler::class)->forObject($basket)->rules)
            ->toHaveKeys(['byRegion.north.code', 'byRegion.south.code']);
    });

    it('flattens the iterable into nested data arrays', function () {
        $basket = new Basket();
        $basket->shipTo = [new Country('NL')];

        expect(app(RuleCompiler::class)->forObject($basket)->data['shipTo'])->toBe([['code' => 'NL']]);
    });

    it('descends into a Collection instead of the collection own internals', function () {
        $basket = new Basket();
        $basket->customers = new Collection([new Customer(), new Customer()]);

        $compiled = app(RuleCompiler::class)->forObject($basket);

        expect($compiled->rules)->toHaveKeys(['customers.0.name', 'customers.0.country.code', 'customers.1.name']);
        expect($compiled->data['customers'][0]['country']['code'])->toBe('NL');
        expect(Validator::makeFromObject($basket)->passes())->toBeTrue();
    });

    it('gives each item its own context, so per-item closures resolve independently', function () {
        $wants = new ConditionalForm();
        $wants->subscribe = true;

        $basket = new Basket();
        $basket->forms = [new ConditionalForm(), $wants];

        $validator = Validator::makeFromObject($basket);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->keys())->toBe(['forms.1.email']);
    });

    it('emits nothing for an empty iterable', function () {
        $rules = app(RuleCompiler::class)->forObject(new Basket())->rules;

        expect($rules)->toHaveKey('shipTo');
        expect(array_filter(array_keys($rules), static fn(string $key): bool => str_starts_with($key, 'shipTo.')))->toBe([]);
        expect(Validator::makeFromObject(new Basket())->passes())->toBeTrue();
    });
});

describe('allowed element types', function () {
    it('fails an item that is not an allowed type', function () {
        $basket = new Basket();
        $basket->customers = new Collection([new Customer(), new Country('NL')]);

        $validator = Validator::makeFromObject($basket);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->keys())->toBe(['customers.1']);
        expect($validator->errors()->first('customers.1'))->toContain('must be Customer');
        expect($validator->errors()->first('customers.1'))->toContain('Country given');
    });

    it('does not validate a disallowed item against its own class rules', function () {
        $basket = new Basket();
        $basket->customers = new Collection([new Country('TOO LONG')]);

        expect(app(RuleCompiler::class)->forObject($basket)->rules)->not->toHaveKey('customers.0.code');
    });

    it('fails a non-object item', function () {
        $basket = new Basket();
        $basket->shipTo = ['not an object', 42];

        $validator = Validator::makeFromObject($basket);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->keys())->toBe(['shipTo.0', 'shipTo.1']);
        expect($validator->errors()->first('shipTo.0'))->toContain('string given');
    });

    it('accepts any of several declared classes', function () {
        $basket = new Basket();
        $basket->mixed = [new Country('NL'), new Customer()];

        expect(app(RuleCompiler::class)->forObject($basket)->rules)->toHaveKeys(['mixed.0.code', 'mixed.1.name']);
        expect(Validator::makeFromObject($basket)->passes())->toBeTrue();
    });

    it('accepts a subclass of an allowed type', function () {
        $basket = new Basket();
        $basket->shipTo = [new class ('NL') extends Country {}];

        expect(Validator::makeFromObject($basket)->passes())->toBeTrue();
    });
});

describe('scalar element rules', function () {
    it('applies rules to every element of a scalar iterable', function () {
        $basket = new Basket();
        $basket->recipients = ['a@example.com', 'nope', 'b@example.com'];

        $compiled = app(RuleCompiler::class)->forObject($basket);

        expect($compiled->rules['recipients.1'])->toBe(['email']);
        expect($compiled->data['recipients'])->toBe(['a@example.com', 'nope', 'b@example.com']);
    });

    it('fails only the offending element', function () {
        $basket = new Basket();
        $basket->recipients = ['a@example.com', 'nope', 'b@example.com'];

        $validator = Validator::makeFromObject($basket);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->keys())->toBe(['recipients.1']);
    });

    it('applies several element rules in order', function () {
        $basket = new Basket();
        $basket->scores = [3, 99];

        expect(app(RuleCompiler::class)->forObject($basket)->rules['scores.1'])->toBe(['integer', 'between:1,10']);
        expect(Validator::makeFromObject($basket)->errors()->keys())->toBe(['scores.1']);
    });

    it('passes a fully valid scalar iterable', function () {
        $basket = new Basket();
        $basket->recipients = ['a@example.com'];
        $basket->scores = [1, 10];

        expect(Validator::makeFromObject($basket)->passes())->toBeTrue();
    });
});

describe('composing #[ListOf] with #[Each]', function () {
    it('validates the elements and applies the element rules', function () {
        $basket = new Basket();
        $basket->composed = [new Country('NL')];

        $rules = app(RuleCompiler::class)->forObject($basket)->rules;

        expect($rules['composed.0'])->toBe(['required']);
        expect($rules['composed.0.code'])->toBe(['string', 'size:2']);
        expect(Validator::makeFromObject($basket)->passes())->toBeTrue();
    });

    it('reports the type mismatch on a bad element', function () {
        $basket = new Basket();
        $basket->composed = [new Customer()];

        $validator = Validator::makeFromObject($basket);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('composed.0'))->toContain('must be Country, Customer given');
        expect(app(RuleCompiler::class)->forObject($basket)->rules)->not->toHaveKey('composed.0.name');
    });
});

describe('interface-typed properties', function () {
    it('narrows a #[Valid] property to the allowed implementations', function () {
        expect(Validator::makeFromObject(new Narrowed())->passes())->toBeTrue();
    });

    it('fails an implementation the narrowing does not name', function () {
        $wrong = new Narrowed(new Cash());

        $validator = Validator::makeFromObject($wrong);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('method'))->toContain('must be Invoice, Cash given');
        expect(app(RuleCompiler::class)->forObject($wrong)->rules)->not->toHaveKey('method.till');
    });

    it('descends into an interface-typed property, which is not a class_exists() hit', function () {
        expect(app(RuleCompiler::class)->forObject(new AnyPayable())->rules)->toHaveKey('method.till');
        expect(app(RuleCompiler::class)->forObject(new AnyPayable(new Invoice()))->rules)->toHaveKey('method.number');
        expect(Validator::makeFromObject(new AnyPayable(new Invoice('X')))->fails())->toBeTrue();
    });
});

describe('misused nesting attributes', function () {
    it('redirects #[Valid] on an iterable to #[ListOf]', function () {
        expect(fn() => app(RuleFinder::class)->find(ValidOnIterable::class))
            ->toThrow(LogicException::class, 'Use #[ListOf(Thing::class)] instead');
    });

    it('redirects #[ListOf] on a single object to #[Valid]', function () {
        expect(fn() => app(RuleFinder::class)->find(ListOfOnObject::class))
            ->toThrow(LogicException::class, 'Use #[Valid] for a single object');
    });

    it('rejects both nesting attributes on one member', function () {
        expect(fn() => app(RuleFinder::class)->find(BothNestingAttributes::class))
            ->toThrow(LogicException::class, 'either one object or many');
    });

    it('redirects a class passed to #[Each] towards #[ListOf]', function () {
        expect(fn() => new Each(Country::class))
            ->toThrow(LogicException::class, 'Use #[ListOf(');
    });

    it('rejects a #[ListOf] class that does not exist', function () {
        expect(fn() => new ListOf('Tests\Fixtures\Validation\Contry'))
            ->toThrow(LogicException::class, 'no such class or interface');
    });
});
