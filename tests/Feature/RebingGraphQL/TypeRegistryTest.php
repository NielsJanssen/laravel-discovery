<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use GraphQL\Type\Definition\Type as GraphQLType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredAction;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\GraphQLDiscovery;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\NullType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Position;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeKind;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRef;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRegistry;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use Tests\Fixtures\RebingGraphQL\Types\Book;
use Tests\Fixtures\RebingGraphQL\Types\ClassTypedPaginatedQuery;
use Tests\Fixtures\RebingGraphQL\Types\ListOfQuery;
use Tests\Fixtures\RebingGraphQL\Types\TypeAndOfQuery;
use Workbench\App\GraphQL\Types\BookType;

beforeEach(function () {
    app()->forgetInstance(TypeRegistry::class);
    GraphQL::addType(BookType::class, 'Book');
});

/**
 * @return array<string, DiscoveredAction> keyed by method name
 */
function discoverTypeFixture(string $class): array
{
    $discovery = app(GraphQLDiscovery::class);
    $discovery->setItems(new DiscoveryItems());

    $discovery->discover(
        new DiscoveryLocation('Tests\\Fixtures\\RebingGraphQL\\Types', dirname(__DIR__, 2) . '/Fixtures/RebingGraphQL/Types'),
        new ClassReflector($class),
    );

    $items = [];

    foreach ($discovery->getItems() as $item) {
        $items[$item->method] = $item;
    }

    return $items;
}

function discoveredFieldType(DiscoveredAction $action): string
{
    return (string) $action->createType(app())->type();
}

describe('TypeRef', function () {
    it('classifies a type: or of: string', function (string $type, string $property) {
        expect(TypeRef::from($type)->{$property})->toBe($type);
    })->with([
        'php scalar' => ['string', 'scalar'],
        'void' => ['void', 'scalar'],
        'built-in name' => ['ID', 'scalar'],
        'class-string' => [Book::class, 'class'],
        'type name' => ['Book', 'name'],
    ]);

    it('rejects an unknown scalar', function () {
        expect(fn() => TypeRef::scalar('Date'))->toThrow(\InvalidArgumentException::class, 'got [Date]');
    });

    it('rejects a class that does not exist', function () {
        expect(fn() => TypeRef::class('Tests\\Missing'))->toThrow(\InvalidArgumentException::class, 'got [Tests\\Missing]');
    });

    it('survives serialization', function () {
        $ref = TypeRef::class(Book::class, list: true, nullable: true, nullableItems: true);

        expect(unserialize(serialize($ref)))->toEqual($ref);
    });
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

    it('resolves void to the Null scalar', function () {
        expect(app(TypeRegistry::class)->resolve(TypeRef::scalar('void', nullable: true), Position::Output))
            ->toBeInstanceOf(NullType::class);
    });

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
        $registry->register(Book::class, 'Book', TypeKind::Object);

        expect((string) $registry->resolve(TypeRef::class(Book::class), Position::Output))->toBe('Book!')
            ->and($registry->classOf('Book'))->toBe(Book::class)
            ->and($registry->kindOf(Book::class, Position::Output))->toBe(TypeKind::Object);
    });

    it('names an unregistered class in the error', function () {
        expect(fn() => app(TypeRegistry::class)->resolve(TypeRef::class(Book::class), Position::Output))
            ->toThrow(\RuntimeException::class, Book::class . ' is not a registered GraphQL output type.');
    });

    it('rejects a class registered for the other position', function () {
        $registry = app(TypeRegistry::class);
        $registry->register(Book::class, 'Book', TypeKind::Object);

        expect(fn() => $registry->resolve(TypeRef::class(Book::class), Position::Input))
            ->toThrow(\RuntimeException::class, 'registered as object type [Book], which cannot be used in input position');
    });

    it('picks the registration that fits the position', function () {
        $registry = app(TypeRegistry::class);
        $registry->register(Book::class, 'Book', TypeKind::Object);
        $registry->register(Book::class, 'BookInput', TypeKind::Input);

        expect($registry->nameOf(Book::class, Position::Output))->toBe('Book')
            ->and($registry->nameOf(Book::class, Position::Input))->toBe('BookInput')
            ->and($registry->has(Book::class, Position::Input))->toBeTrue();
    });

    it('allows an enum in both positions', function () {
        $registry = app(TypeRegistry::class);
        $registry->register(Book::class, 'Shelf', TypeKind::Enum);

        expect($registry->nameOf(Book::class, Position::Output))->toBe('Shelf')
            ->and($registry->nameOf(Book::class, Position::Input))->toBe('Shelf');
    });

    it('rejects a name that belongs to another class', function () {
        $registry = app(TypeRegistry::class);
        $registry->register(Book::class, 'Book', TypeKind::Object);

        expect(fn() => $registry->register(ListOfQuery::class, 'Book', TypeKind::Object))
            ->toThrow(\LogicException::class, 'that name already belongs to ' . Book::class);
    });

    it('rejects a second name for the same class and kind', function () {
        $registry = app(TypeRegistry::class);
        $registry->register(Book::class, 'Book', TypeKind::Object);

        expect(fn() => $registry->register(Book::class, 'Novel', TypeKind::Object))
            ->toThrow(\LogicException::class, 'it is already registered as [Book]');
    });

    it('is a container singleton', function () {
        expect(app(TypeRegistry::class))->toBe(app(TypeRegistry::class));
    });
});

describe('type: and of: on actions', function () {
    it('makes of: a non-null list of non-null items', function () {
        $action = discoverTypeFixture(ListOfQuery::class)['tags'];

        expect($action->action->list)->toBeTrue()
            ->and(discoveredFieldType($action))->toBe('[String!]!');
    });

    it('allows null items with nullableItems:', function () {
        expect(discoveredFieldType(discoverTypeFixture(ListOfQuery::class)['sparseTags']))->toBe('[String]!');
    });

    it('resolves of: with a registered class-string', function () {
        app(TypeRegistry::class)->register(Book::class, 'Book', TypeKind::Object);

        expect(discoveredFieldType(discoverTypeFixture(ListOfQuery::class)['books']))->toBe('[Book!]!');
    });

    it('widens of: to a nullable list for a nullable return', function () {
        expect(discoveredFieldType(discoverTypeFixture(ListOfQuery::class)['maybeBooks']))->toBe('[Book!]');
    });

    it('resolves type: with a registered class-string', function () {
        app(TypeRegistry::class)->register(Book::class, 'Book', TypeKind::Object);

        expect(discoveredFieldType(discoverTypeFixture(ListOfQuery::class)['book']))->toBe('Book');
    });

    it('resolves type: with a built-in scalar name', function () {
        expect(discoveredFieldType(discoverTypeFixture(ListOfQuery::class)['bookId']))->toBe('ID!');
    });

    it('reports an unregistered class-string when the type is built', function () {
        $action = discoverTypeFixture(ListOfQuery::class)['book'];

        expect(fn() => discoveredFieldType($action))
            ->toThrow(\RuntimeException::class, Book::class . ' is not a registered GraphQL output type.');
    });

    it('rejects type: and of: together at discovery', function () {
        expect(fn() => discoverTypeFixture(TypeAndOfQuery::class))
            ->toThrow(\LogicException::class, TypeAndOfQuery::class . '::books sets both type: and of:');
    });

    it('keeps the discovered type reference serializable', function () {
        $action = discoverTypeFixture(ListOfQuery::class)['books'];

        expect(unserialize(serialize($action))->returnType)->toEqual(TypeRef::class(Book::class, list: true));
    });

    it('gives #[Paginated] the registered name of a class-string type', function () {
        app(TypeRegistry::class)->register(Book::class, 'Book', TypeKind::Object);

        $action = discoverTypeFixture(ClassTypedPaginatedQuery::class)['resolve'];
        $type = $action->createType(app())->type();

        expect($type)->toBeInstanceOf(GraphQLType::class)
            ->and($type->name)->toBe('BookPagination')
            ->and($action->action->type)->toBe(Book::class);
    });
});
