<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredAction;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\GraphQLDiscovery;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use Tests\Fixtures\RebingGraphQL\DeprecatedQuery;
use Tests\Fixtures\RebingGraphQL\DescribedQuery;
use Tests\Fixtures\RebingGraphQL\MissingTypeQuery;
use Tests\Fixtures\RebingGraphQL\NonScalarQuery;
use Tests\Fixtures\RebingGraphQL\NullableScalarReturnQuery;
use Tests\Fixtures\RebingGraphQL\OptionalArgQuery;
use Tests\Fixtures\RebingGraphQL\ScalarReturnQuery;
use Tests\Fixtures\RebingGraphQL\SchemaExplicitArgQuery;
use Tests\Fixtures\RebingGraphQL\SchemaMethodOverridesClassQuery;
use Tests\Fixtures\RebingGraphQL\SchemaOnClassQuery;
use Tests\Fixtures\RebingGraphQL\SchemaOnMethodQuery;
use Tests\Fixtures\RebingGraphQL\VoidQuery;

function discoverGraphQL(string ...$classes): GraphQLDiscovery
{
    $discovery = app(GraphQLDiscovery::class);
    $discovery->setItems(new DiscoveryItems());

    $location = new DiscoveryLocation(
        namespace: 'Tests\\Fixtures\\GraphQL',
        path: dirname(__DIR__, 2) . '/Fixtures/RebingGraphQL',
    );

    foreach ($classes as $class) {
        $discovery->discover($location, new ClassReflector($class));
    }

    return $discovery;
}

it('returns books from the books query', function () {
    $this->postJson('/graphql', [
        'query' => '{ books { id title author } }',
    ])
        ->assertOk()
        ->assertJsonPath('data.books.0.title', 'The Great Gatsby')
        ->assertJsonPath('data.books.1.title', '1984')
        ->assertJsonPath('data.books.2.title', 'To Kill a Mockingbird');
});

it('filters books by title', function () {
    $this->postJson('/graphql', [
        'query' => '{ books(title: "1984") { id title } }',
    ])
        ->assertOk()
        ->assertJsonCount(1, 'data.books')
        ->assertJsonPath('data.books.0.title', '1984');
});

it('returns authors from the authors query', function () {
    $this->postJson('/graphql', [
        'query' => '{ authors { id name } }',
    ])
        ->assertOk()
        ->assertJsonPath('data.authors.0.name', 'F. Scott Fitzgerald')
        ->assertJsonPath('data.authors.1.name', 'George Orwell')
        ->assertJsonPath('data.authors.2.name', 'Harper Lee');
});

it('resolves a scalar return type query', function () {
    $this->postJson('/graphql', [
        'query' => '{ greet(name: "World") }',
    ])
        ->assertOk()
        ->assertJsonPath('data.greet', 'Hello, World!');
});

it('infers scalar return type from method return type hint', function () {
    $discovery = discoverGraphQL(ScalarReturnQuery::class);
    /** @var DiscoveredAction[] $items */
    $items = iterator_to_array($discovery->getItems());

    expect($items)->toHaveCount(1)
        ->and($items[0]->action->type)->toBe('string')
        ->and($items[0]->action->nullable)->toBeFalse();
});

it('infers nullable scalar return type and sets nullable on the action', function () {
    $discovery = discoverGraphQL(NullableScalarReturnQuery::class);
    $items = iterator_to_array($discovery->getItems());

    expect($items[0]->action->type)->toBe('string')
        ->and($items[0]->action->nullable)->toBeTrue();
});

it('stores default values and widens nullable for optional arguments during discovery', function () {
    $discovery = discoverGraphQL(OptionalArgQuery::class);
    $items = iterator_to_array($discovery->getItems());
    [$a, $b] = $items[0]->args;

    expect($a->nullable)->toBeTrue()
        ->and($a->hasDefault)->toBeTrue()
        ->and($a->defaultValue)->toBe(0)
        ->and($b->hasDefault)->toBeTrue()
        ->and($b->defaultValue)->toBe(0);
});

it('throws during discovery when a non-scalar argument is missing #[Arg]', function () {
    expect(fn() => discoverGraphQL(NonScalarQuery::class))
        ->toThrow(\RuntimeException::class, '#[Arg(type:');
});

it('throws during discovery when type is missing and return type is non-scalar', function () {
    expect(fn() => discoverGraphQL(MissingTypeQuery::class))
        ->toThrow(\RuntimeException::class, 'scalar return type');
});

it('maps void return type to the Null scalar type', function () {
    $discovery = discoverGraphQL(VoidQuery::class);
    $items = iterator_to_array($discovery->getItems());

    expect($items[0]->action->type)->toBe('void')
        ->and($items[0]->action->nullable)->toBeTrue();
});


it('resolves a query with optional args using defaults when omitted', function () {
    $this->postJson('/graphql', ['query' => '{ add }'])
        ->assertOk()
        ->assertJsonPath('data.add', 0);
});

it('resolves a query with optional args when values are provided', function () {
    $this->postJson('/graphql', ['query' => '{ add(a: 3, b: 4) }'])
        ->assertOk()
        ->assertJsonPath('data.add', 7);
});

it('resolves a query with optional args using default when null is passed', function () {
    $this->postJson('/graphql', ['query' => '{ add(a: 5, b: null) }'])
        ->assertOk()
        ->assertJsonPath('data.add', 5);
});

it('resolves a void mutation returning null', function () {
    $this->postJson('/graphql', [
        'query' => 'mutation { clearCache }',
    ])
        ->assertOk()
        ->assertJsonPath('data.clearCache', null);
});

it('applies a method-level #[Schema] decorator to the action', function () {
    $items = iterator_to_array(discoverGraphQL(SchemaOnMethodQuery::class)->getItems());

    expect($items[0]->action->schema)->toBe('admin');
});

it('applies a class-level #[Schema] decorator to every action method', function () {
    $items = iterator_to_array(discoverGraphQL(SchemaOnClassQuery::class)->getItems());

    expect($items)->toHaveCount(2)
        ->and($items[0]->action->schema)->toBe('admin')
        ->and($items[1]->action->schema)->toBe('admin');
});

it('lets a method-level #[Schema] decorator override the class-level one', function () {
    $items = iterator_to_array(discoverGraphQL(SchemaMethodOverridesClassQuery::class)->getItems());

    $bySchema = array_column(array_map(
        fn($item) => ['name' => $item->action->name, 'schema' => $item->action->schema],
        $items,
    ), 'schema', 'name');

    expect($bySchema)->toBe([
        'methodWins' => 'public',
        'classFallback' => 'admin',
    ]);
});

it('preserves an explicit schema argument on #[Query] over decorators', function () {
    $items = iterator_to_array(discoverGraphQL(SchemaExplicitArgQuery::class)->getItems());

    expect($items[0]->action->schema)->toBe('reports');
});

it('routes decorated actions to their declared schema in graphql.schemas config', function () {
    config()->set('graphql.schemas', []);

    $discovery = discoverGraphQL(SchemaOnMethodQuery::class, SchemaOnClassQuery::class);
    $discovery->apply();

    $schemas = config('graphql.schemas');

    expect($schemas)->toHaveKey('admin')
        ->and($schemas['admin']['query'])->toHaveKey('methodLevel')
        ->and($schemas['admin']['query'])->toHaveKey('classLevelQuery')
        ->and($schemas['admin']['mutation'])->toHaveKey('classLevelMutation')
        ->and($schemas['default'] ?? [])->not->toHaveKey('query');
});

it('routes undecorated actions to graphql.default_schema when configured', function () {
    config()->set('graphql.schemas', []);
    config()->set('graphql.default_schema', 'custom');

    $discovery = discoverGraphQL(ScalarReturnQuery::class);
    $discovery->apply();

    $schemas = config('graphql.schemas');

    expect($schemas)->toHaveKey('custom')
        ->and($schemas['custom']['query'] ?? [])->not->toBeEmpty()
        ->and($schemas)->not->toHaveKey('default');
});

it('uses the Rebing default schema "default" when graphql.default_schema is unchanged', function () {
    config()->set('graphql.schemas', []);

    expect(config('graphql.default_schema'))->toBe('default');

    $discovery = discoverGraphQL(ScalarReturnQuery::class);
    $discovery->apply();

    $schemas = config('graphql.schemas');

    expect($schemas)->toHaveKey('default')
        ->and($schemas['default']['query'] ?? [])->not->toBeEmpty();
});

it('keeps decorated actions in their declared schema even when graphql.default_schema is set', function () {
    config()->set('graphql.schemas', []);
    config()->set('graphql.default_schema', 'custom');

    $discovery = discoverGraphQL(SchemaOnMethodQuery::class, ScalarReturnQuery::class);
    $discovery->apply();

    $schemas = config('graphql.schemas');

    expect($schemas['admin']['query'])->toHaveKey('methodLevel')
        ->and($schemas['custom']['query'] ?? [])->not->toBeEmpty()
        ->and($schemas['custom']['query'] ?? [])->not->toHaveKey('methodLevel');
});

it('exposes #[Query(description: ...)] on the discovered action and the Field attributes', function () {
    $items = iterator_to_array(discoverGraphQL(DescribedQuery::class)->getItems());
    /** @var DiscoveredAction $item */
    $item = $items[0];

    $field = $item->createType(app());

    expect($item->action->description)->toBe('Returns a greeting')
        ->and($field->attributes())->toMatchArray([
            'name' => 'described',
            'description' => 'Returns a greeting',
        ]);
});

it('maps native #[\Deprecated] on methods and parameters to GraphQL deprecationReason', function () {
    $items = iterator_to_array(discoverGraphQL(DeprecatedQuery::class)->getItems());
    /** @var DiscoveredAction $item */
    $item = $items[0];

    $field = $item->createType(app());

    expect($item->deprecationReason)->toBe('Use newGreet instead (since 2.0.0)')
        ->and($item->args[0]->deprecationReason)->toBe('Pass name via context')
        ->and($field->attributes())->toHaveKey('deprecationReason', 'Use newGreet instead (since 2.0.0)')
        ->and($field->args()['name'])->toHaveKey('deprecationReason', 'Pass name via context');
});
