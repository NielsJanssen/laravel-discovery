<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use Acme\DoesNotExist;
use Illuminate\Support\Collection;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\TypeInferrer;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\TypeMapperRegistry;
use ReflectionMethod;
use ReflectionProperty;
use ReflectionType;
use stdClass;

/**
 * @return object the sources of the types the inferrer is asked about
 */
function inferenceSource(): object
{
    return new class {
        public int|string $union;

        public \Countable&\Traversable $intersection;

        public mixed $anything;

        public $untyped;

        public array $list;

        public iterable $iterable;

        public Collection $collection;

        public DoesNotExist $unknown;

        public string $title;

        public stdClass $object;

        public function nothing(): void {}
    };
}

function inferenceType(string $source): ?ReflectionType
{
    return $source === 'nothing'
        ? new ReflectionMethod(inferenceSource(), 'nothing')->getReturnType()
        : new ReflectionProperty(inferenceSource()::class, $source)->getType();
}

function typeInferrer(): TypeInferrer
{
    return new TypeInferrer(new TypeMapperRegistry());
}

/**
 * @param  'output'|'input'  $position
 */
function inferFrom(string $position, string $source, ?string $type = null, ?string $of = null): mixed
{
    return typeInferrer()->{$position}(inferenceType($source), stdClass::class, 'Property Acme::$value', 'Field', $type, $of);
}

dataset('inference positions', ['output', 'input']);

it('rejects a union type without type:', function (string $position) {
    expect(fn() => inferFrom($position, 'union'))->toThrow(LogicException::class, 'Property Acme::$value has the union type string|int, which has no GraphQL');
})->with('inference positions');

it('rejects an intersection type without type:', function (string $position) {
    expect(fn() => inferFrom($position, 'intersection'))->toThrow(LogicException::class, 'Property Acme::$value has the intersection type');
})->with('inference positions');

it('rejects mixed without type:', function (string $position) {
    expect(fn() => inferFrom($position, 'anything'))->toThrow(LogicException::class, 'Property Acme::$value declares the type mixed. Add a PHP type, or name the GraphQL type with #[Field(type: ...)]');
})->with('inference positions');

it('rejects an untyped member without type:', function (string $position) {
    expect(fn() => inferFrom($position, 'untyped'))->toThrow(LogicException::class, 'Property Acme::$value declares no type');
})->with('inference positions');

it('rejects void', function (string $position) {
    expect(fn() => inferFrom($position, 'nothing'))->toThrow(LogicException::class, 'Property Acme::$value has type void, which has no GraphQL');
})->with('inference positions');

it('asks for of: on an array, an iterable or a Collection', function (string $position, string $source, string $type) {
    expect(fn() => inferFrom($position, $source))->toThrow(LogicException::class, "Property Acme::\$value has type $type, which needs #[Field(of: ...)]");
})->with('inference positions')->with([
    'array' => ['list', 'array'],
    'iterable' => ['iterable', 'iterable'],
    'Collection' => ['collection', Collection::class],
]);

it('rejects an unknown class without type:', function (string $position) {
    expect(fn() => inferFrom($position, 'unknown'))->toThrow(LogicException::class, 'Property Acme::$value has type Acme\DoesNotExist, which has no GraphQL');
})->with('inference positions');

it('rejects type: together with of:', function (string $position) {
    expect(fn() => inferFrom($position, 'list', 'Book', 'Book'))->toThrow(LogicException::class, 'Property Acme::$value sets both type: and of: on #[Field]');
})->with('inference positions');

it('accepts an explicit type: or of: where the PHP type would be rejected', function (string $position) {
    expect(inferFrom($position, 'union', 'String')->scalar)->toBe('String')
        ->and(inferFrom($position, 'list', of: 'String')->list)->toBeTrue();
})->with('inference positions');

it('infers a scalar in both positions', function (string $position) {
    expect(inferFrom($position, 'title')->scalar)->toBe('string');
})->with('inference positions');

it('rejects type: together with of: on an action', function () {
    app(TypeInferrer::class)->assertNotBothTypeAndOf('Book', null, 'Method A::b', 'Query');
    app(TypeInferrer::class)->assertNotBothTypeAndOf(null, 'Book', 'Method A::b', 'Query');

    expect(fn() => app(TypeInferrer::class)->assertNotBothTypeAndOf('Book', 'Book', 'Method A::b', 'Query'))
        ->toThrow(LogicException::class, 'Method A::b sets both type: and of: on #[Query]. Use of: for a list of that type, or type: for a single value.');
});
