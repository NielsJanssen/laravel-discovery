<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use Illuminate\Container\Container;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Position;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeKind;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRef;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRegistry;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Tests\Fixtures\RebingGraphQL\Types\Shelf;
use Tests\Fixtures\RebingGraphQL\Types\ShelfQuery;
use Workbench\App\GraphQL\Types\BookType;

beforeEach(function () {
    app()->forgetInstance(TypeRegistry::class);
    GraphQL::addType(BookType::class, 'Book');
});

describe('TypeRef', function () {
    it('classifies a type: or of: string', function (string $type, string $property) {
        expect(TypeRef::from($type)->{$property})->toBe($type);
    })->with([
        'php scalar' => ['string', 'scalar'],
        'void' => ['void', 'scalar'],
        'built-in name' => ['ID', 'scalar'],
        'class-string' => [Shelf::class, 'class'],
        'type name' => ['Book', 'name'],
    ]);

    it('rejects an unknown scalar', function () {
        expect(fn() => TypeRef::scalar('Date'))->toThrow(\InvalidArgumentException::class, 'got [Date]');
    });

    it('rejects a class that does not exist', function () {
        expect(fn() => TypeRef::class('Tests\\Missing'))->toThrow(\InvalidArgumentException::class, 'got [Tests\\Missing]');
    });

    it('survives serialization', function () {
        $ref = TypeRef::class(Shelf::class, list: true, nullable: true, nullableItems: true);

        expect(unserialize(serialize($ref)))->toEqual($ref);
    });
});

it('is a singleton even without the service provider binding it', function () {
    $container = new Container();

    expect($container->make(TypeRegistry::class))->toBe($container->make(TypeRegistry::class));
});

describe('TypeRegistry::resolve()', function () {
    it('resolves every scalar', function (string $scalar, string $expected) {
        $type = app(TypeRegistry::class)->resolve(TypeRef::scalar($scalar, nullable: true), Position::Output);

        expect((string) $type)->toBe($expected);
    })->with([
        ['string', 'String'],
        ['int', 'Int'],
        ['float', 'Float'],
        ['bool', 'Boolean'],
        ['void', 'Null'],
        ['ID', 'ID'],
        ['String', 'String'],
        ['Int', 'Int'],
        ['Float', 'Float'],
        ['Boolean', 'Boolean'],
    ]);

    it('rejects void in input position', function () {
        expect(fn() => app(TypeRegistry::class)->resolve(TypeRef::scalar('void'), Position::Input))
            ->toThrow(\LogicException::class, 'void type has no input form');
    });

    it('wraps in list and non-null', function (TypeRef $ref, string $expected) {
        expect((string) app(TypeRegistry::class)->resolve($ref, Position::Output))->toBe($expected);
    })->with([
        'non-null' => [TypeRef::scalar('int'), 'Int!'],
        'nullable' => [TypeRef::scalar('int', nullable: true), 'Int'],
        'list' => [TypeRef::scalar('int', list: true), '[Int!]!'],
        'nullable list' => [TypeRef::scalar('int', list: true, nullable: true), '[Int!]'],
        'nullable items' => [TypeRef::scalar('int', list: true, nullableItems: true), '[Int]!'],
        'nullable list of nullable items' => [TypeRef::scalar('int', list: true, nullable: true, nullableItems: true), '[Int]'],
    ]);

    it('resolves a plain name through Rebing', function () {
        expect((string) app(TypeRegistry::class)->resolve(TypeRef::named('Book', list: true), Position::Output))
            ->toBe('[Book!]!');
    });

    it('resolves a registered class to its GraphQL type', function () {
        $registry = app(TypeRegistry::class);
        $registry->register(Shelf::class, 'Book', TypeKind::Object);

        expect((string) $registry->resolve(TypeRef::class(Shelf::class), Position::Output))->toBe('Book!')
            ->and($registry->kindOf(Shelf::class, Position::Output))->toBe(TypeKind::Object);
    });

    it('names an unregistered class in the error', function () {
        expect(fn() => app(TypeRegistry::class)->resolve(TypeRef::class(Shelf::class), Position::Output))
            ->toThrow(\RuntimeException::class, Shelf::class . ' is not a registered GraphQL output type.');
    });

    it('rejects a class registered for the other position', function () {
        $registry = app(TypeRegistry::class);
        $registry->register(Shelf::class, 'Shelf', TypeKind::Object);

        expect(fn() => $registry->resolve(TypeRef::class(Shelf::class), Position::Input))
            ->toThrow(\RuntimeException::class, 'registered as object type [Shelf], which cannot be used in input position');
    });

    it('picks the registration that fits the position', function () {
        $registry = app(TypeRegistry::class);
        $registry->register(Shelf::class, 'Shelf', TypeKind::Object);
        $registry->register(Shelf::class, 'ShelfInput', TypeKind::Input);

        expect($registry->nameOf(Shelf::class, Position::Output))->toBe('Shelf')
            ->and($registry->nameOf(Shelf::class, Position::Input))->toBe('ShelfInput')
            ->and($registry->has(Shelf::class, Position::Input))->toBeTrue();
    });

    it('allows an enum in both positions', function () {
        $registry = app(TypeRegistry::class);
        $registry->register(Shelf::class, 'Shelf', TypeKind::Enum);

        expect($registry->nameOf(Shelf::class, Position::Output))->toBe('Shelf')
            ->and($registry->nameOf(Shelf::class, Position::Input))->toBe('Shelf');
    });

    it('rejects a name that belongs to another class', function () {
        $registry = app(TypeRegistry::class);
        $registry->register(Shelf::class, 'Shelf', TypeKind::Object);

        expect(fn() => $registry->register(ShelfQuery::class, 'Shelf', TypeKind::Object))
            ->toThrow(\LogicException::class, 'that name already belongs to ' . Shelf::class);
    });

    it('rejects a second name for the same class and kind', function () {
        $registry = app(TypeRegistry::class);
        $registry->register(Shelf::class, 'Shelf', TypeKind::Object);

        expect(fn() => $registry->register(Shelf::class, 'Novel', TypeKind::Object))
            ->toThrow(\LogicException::class, 'it is already registered as [Shelf]');
    });

    it('is a container singleton', function () {
        expect(app(TypeRegistry::class))->toBe(app(TypeRegistry::class));
    });
});

describe('type: on actions', function () {
    it('reports an unregistered class-string when the type is built', function () {
        $action = discoveredActions(ShelfQuery::class)['shelf'];

        expect(fn() => (string) $action->createType(app())->type())
            ->toThrow(\RuntimeException::class, Shelf::class . ' is not a registered GraphQL output type.');
    });
});
