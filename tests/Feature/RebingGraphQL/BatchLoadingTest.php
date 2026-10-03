<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use GraphQL\Error\DebugFlag;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Server\OperationParams as BaseOperationParams;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\GraphQLDiscoveryServiceProvider;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\Loaders;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\LoadersExecutionMiddleware;
use Rebing\GraphQL\Support\ExecutionMiddleware\AddAuthUserContextValueMiddleware;
use Rebing\GraphQL\Support\ExecutionMiddleware\AutomaticPersistedQueriesMiddleware;
use Rebing\GraphQL\Support\ExecutionMiddleware\ValidateOperationParamsMiddleware;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Rebing\GraphQL\Support\OperationParams;
use Tests\Fixtures\RebingGraphQL\Loaders\Crate;
use Tests\Fixtures\RebingGraphQL\Loaders\CrateQueries;
use Tests\Fixtures\RebingGraphQL\Loaders\FileLoader;
use Tests\Fixtures\RebingGraphQL\Loaders\Invalid\AbstractLoader;
use Tests\Fixtures\RebingGraphQL\Loaders\Invalid\AbstractLoaderField;
use Tests\Fixtures\RebingGraphQL\Loaders\Invalid\ClosureOption;
use Tests\Fixtures\RebingGraphQL\Loaders\Invalid\KeyWithoutKey;
use Tests\Fixtures\RebingGraphQL\Loaders\Invalid\KeyWithoutModel;
use Tests\Fixtures\RebingGraphQL\Loaders\Invalid\ManyOnSingleField;
use Tests\Fixtures\RebingGraphQL\Loaders\Invalid\NotALoader;
use Tests\Fixtures\RebingGraphQL\Loaders\Invalid\PositionalOption;
use Tests\Fixtures\RebingGraphQL\Loaders\Invalid\ProtectedKey;
use Tests\Fixtures\RebingGraphQL\Loaders\Invalid\RelationOnPlainClass;
use Tests\Fixtures\RebingGraphQL\Loaders\Invalid\RelationToMissingMethod;
use Tests\Fixtures\RebingGraphQL\Loaders\Invalid\RelationWithoutType;
use Tests\Fixtures\RebingGraphQL\Loaders\Invalid\SingleOnListField;
use Tests\Fixtures\RebingGraphQL\Loaders\Invalid\SingleOnNonUniqueColumn;
use Tests\Fixtures\RebingGraphQL\Loaders\Invalid\TwoLoaders;
use Tests\Fixtures\RebingGraphQL\Loaders\Invalid\UnknownKey;
use Tests\Fixtures\RebingGraphQL\Loaders\Invalid\UnknownOption;
use Tests\Fixtures\RebingGraphQL\Loaders\LoaderQueries;
use Tests\Fixtures\RebingGraphQL\Loaders\Novel;
use Tests\Fixtures\RebingGraphQL\Loaders\NullLoader;
use Tests\Fixtures\RebingGraphQL\Loaders\Review;
use Tests\Fixtures\RebingGraphQL\Loaders\Writer;
use Tests\Fixtures\RebingGraphQL\Loaders\WrongLengthLoader;
use Workbench\App\Models\User;

const LOADER_SDL = <<<'GRAPHQL'
    type Writer {
      id: ID!
      name: String!
      novels: [Novel!]!
      covers(size: String): [String!]!
      privateFiles: [String!]
      guardedFirst: [String!]
      guardedLast: [String!]
    }

    type Novel {
      title: String!
      author: Writer!
    }

    type Review {
      writer: Writer
      novelsByWriter: [Novel!]!
      id: ID!
      writerId: Int!
      deferredWriter: Writer
    }

    type Query {
      writers: [Writer!]!
      writersWithNovels: [Writer!]!
      novels: [Novel!]!
      reviews: [Review!]!
    }

    GRAPHQL;

/**
 * @return array<string, mixed>
 */
function loaderQuery(string $query): array
{
    DB::flushQueryLog();

    return test()->postJson('/graphql', ['query' => $query])->assertOk()->json();
}

/**
 * @return list<string>
 */
function loggedQueries(): array
{
    return array_column(DB::getQueryLog(), 'query');
}

function seedWriters(): void
{
    $ann = Writer::create(['name' => 'Ann']);
    $bob = Writer::create(['name' => 'Bob']);
    Writer::create(['name' => 'Cy']);

    Novel::create(['writer_id' => $ann->id, 'title' => 'Ann 1']);
    Novel::create(['writer_id' => $bob->id, 'title' => 'Bob 1']);
    Novel::create(['writer_id' => $ann->id, 'title' => 'Ann 2']);
}

describe('wiring LoadersExecutionMiddleware', function () {
    it('puts it in front of Rebing\'s default execution middleware', function () {
        expect(config('graphql.execution_middleware'))->toBe([
            LoadersExecutionMiddleware::class,
            ValidateOperationParamsMiddleware::class,
            AutomaticPersistedQueriesMiddleware::class,
            AddAuthUserContextValueMiddleware::class,
        ]);
    });

    it('puts it in front of a schema\'s own list and leaves a schema without one alone', function () {
        config()->set('graphql.schemas.own', ['query' => [], 'execution_middleware' => [AddAuthUserContextValueMiddleware::class]]);
        config()->set('graphql.schemas.inherits', ['query' => [], 'execution_middleware' => null]);

        app()->register(GraphQLDiscoveryServiceProvider::class, force: true);

        expect(config('graphql.schemas.own.execution_middleware'))->toBe([LoadersExecutionMiddleware::class, AddAuthUserContextValueMiddleware::class])
            ->and(config('graphql.schemas.inherits.execution_middleware'))->toBeNull();
    });

    it('adds it once when the wiring runs again', function () {
        config()->set('graphql.schemas.own', ['query' => [], 'execution_middleware' => [AddAuthUserContextValueMiddleware::class]]);
        $global = config('graphql.execution_middleware');

        app()->register(GraphQLDiscoveryServiceProvider::class, force: true);
        app()->register(GraphQLDiscoveryServiceProvider::class, force: true);

        expect(config('graphql.execution_middleware'))->toBe($global)
            ->and(config('graphql.schemas.own.execution_middleware'))->toBe([LoadersExecutionMiddleware::class, AddAuthUserContextValueMiddleware::class]);
    });
});

describe('discovering batched fields', function () {
    it('prints the fields with the types their #[Field] names', function () {
        expect(schemaSdl(LoaderQueries::class, Writer::class, Novel::class, Review::class))->toBe(LOADER_SDL);

        buildAllSchemas();
    });

    it('survives a serialize round trip', function () {
        $items = iterator_to_array(discoverGraphQL(LoaderQueries::class, Writer::class, Novel::class, Review::class)->getItems(), false);

        foreach ($items as $item) {
            expect(unserialize(serialize($item)))->toEqual($item);
        }

        $writer = array_values(array_filter($items, static fn(mixed $item): bool => $item instanceof DiscoveredType && $item->class === Writer::class))[0];

        expect(array_column($writer->fields, 'typeClass'))->each->toBe(Writer::class);
    });

    it('rejects a field it cannot load', function (string $class, string $message) {
        expect(fn() => discoverGraphQL($class))->toThrow(LogicException::class, $message);
    })->with([
        '#[Relation] without type: or of:' => [
            RelationWithoutType::class,
            'Method ' . RelationWithoutType::class . '::novels() has #[Relation], so its #[Field] needs type: for a single record or of: for a list, as in #[Field(of: Book::class)]. The type is not read from the relation.',
        ],
        '#[Relation] on a class that is not a model' => [
            RelationOnPlainClass::class,
            'Method ' . RelationOnPlainClass::class . '::novels() has #[Relation], but ' . RelationOnPlainClass::class . ' is not an Eloquent model, so it has no relations to load. Use #[Load(KeyLoader::class, ...)] or your own BatchLoader instead.',
        ],
        '#[Relation] naming a missing method' => [
            RelationToMissingMethod::class,
            'Method ' . RelationToMissingMethod::class . "::novels() has #[Relation] for the relation 'books', but " . RelationToMissingMethod::class . ' has no method books(). Name an existing relation method.',
        ],
        'a KeyLoader key: the parent does not have' => [
            UnknownKey::class,
            'Property ' . UnknownKey::class . "::\$writer has #[Load] with key: 'authorId', but " . UnknownKey::class . ' has no public property $authorId. Name a public property of ' . UnknownKey::class . '.',
        ],
        'a KeyLoader key: that is not public' => [
            ProtectedKey::class,
            'Property ' . ProtectedKey::class . "::\$writer has #[Load] with key: 'writerId', but " . ProtectedKey::class . ' has no public property $writerId. Name a public property of ' . ProtectedKey::class . '.',
        ],
        'a single KeyLoader on a column that is not the key' => [
            SingleOnNonUniqueColumn::class,
            'Property ' . SingleOnNonUniqueColumn::class . "::\$novel has #[Load] with column: 'writer_id' and loads a single record, but only the key column 'id' of " . Novel::class . " is known to be unique. Add many: true to load every match, or key on the unique column 'id'.",
        ],
        'a loader that cannot be instantiated' => [
            AbstractLoaderField::class,
            'Property ' . AbstractLoaderField::class . '::$label has #[Load], whose loader ' . AbstractLoader::class . ' cannot be instantiated. Name a concrete BatchLoader class.',
        ],
        'an unknown KeyLoader option' => [
            UnknownOption::class,
            'Property ' . UnknownOption::class . '::$writer has #[Load] with the unknown option colum. KeyLoader takes model, key, column, many.',
        ],
        'a KeyLoader without model:' => [
            KeyWithoutModel::class,
            'Property ' . KeyWithoutModel::class . '::$writer has #[Load] without model:, or with one that is not an Eloquent model. Name the model to load, as in model: Author::class.',
        ],
        'a KeyLoader without key:' => [
            KeyWithoutKey::class,
            'Property ' . KeyWithoutKey::class . "::\$writer has #[Load] without key:. Name the property of the parent that holds the value to match, as in key: 'authorId'.",
        ],
        'many: true on a single field' => [
            ManyOnSingleField::class,
            'Property ' . ManyOnSingleField::class . '::$novel has #[Load] with many: true, which loads a list, but the field is a single value. Use #[Field(of: ...)].',
        ],
        'a single KeyLoader on a list field' => [
            SingleOnListField::class,
            'Property ' . SingleOnListField::class . '::$novels has #[Load], which loads a single record, but the field is a list. Add many: true, or make the field a single value.',
        ],
        'a closure in the options' => [
            ClosureOption::class,
            'Property ' . ClosureOption::class . "::\$label has #[Load] with an option that cannot be cached with discovery (Serialization of 'Closure' is not allowed). Options must be scalars, arrays, enums or other serializable values, never closures.",
        ],
        'an option without a name' => [
            PositionalOption::class,
            'Property ' . PositionalOption::class . "::\$writer has #[Load(KeyLoader, ...)] with an option at position 2. Name every option, as in #[Load(KeyLoader, key: 'authorId')].",
        ],
        'two loaders on one field' => [
            TwoLoaders::class,
            'Method ' . TwoLoaders::class . '::novels() has #[Relation] and #[Load], but a field loads through one loader. Remove all but one.',
        ],
        'a loader that is not a BatchLoader' => [
            NotALoader::class,
            'Property ' . NotALoader::class . '::$label has #[Load], whose loader ' . Novel::class . ' does not implement NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\BatchLoader.',
        ],
    ]);
});

describe('loading in batches', function () {
    beforeEach(function () {
        $this->loadMigrationsFrom(dirname(__DIR__, 2) . '/Fixtures/RebingGraphQL/Loaders/migrations');
        seedWriters();
        FileLoader::$calls = [];
        Review::$registries = [];
        DB::enableQueryLog();

        schemaSdl(LoaderQueries::class, Writer::class, Novel::class, Review::class);
    });

    it('loads a has-many relation for every parent in one query', function () {
        $result = loaderQuery('{ writers { name novels { title } } }');

        expect($result)->toBe(['data' => ['writers' => [
            ['name' => 'Ann', 'novels' => [['title' => 'Ann 1'], ['title' => 'Ann 2']]],
            ['name' => 'Bob', 'novels' => [['title' => 'Bob 1']]],
            ['name' => 'Cy', 'novels' => []],
        ]]])->and(loggedQueries())->toHaveCount(2);
    });

    it('loads a belongs-to relation named after the PHP method, under the field name', function () {
        $result = loaderQuery('{ novels { title author { name } } }');

        expect($result)->toBe(['data' => ['novels' => [
            ['title' => 'Ann 1', 'author' => ['name' => 'Ann']],
            ['title' => 'Bob 1', 'author' => ['name' => 'Bob']],
            ['title' => 'Ann 2', 'author' => ['name' => 'Ann']],
        ]]])->and(loggedQueries())->toHaveCount(2);
    });

    it('batches each level of a nested selection once', function () {
        $result = loaderQuery('{ writers { novels { author { novels { title } } } } }');

        expect($result['data']['writers'][0]['novels'][0]['author']['novels'])->toBe([['title' => 'Ann 1'], ['title' => 'Ann 2']])
            ->and(loggedQueries())->toHaveCount(4);
    });

    it('does not reload a relation that is already loaded', function () {
        $result = loaderQuery('{ writersWithNovels { name novels { title } } }');

        expect($result['data']['writersWithNovels'][0])->toBe(['name' => 'Ann', 'novels' => [['title' => 'Ann 1'], ['title' => 'Ann 2']]])
            ->and(loggedQueries())->toHaveCount(2);
    });

    it('loads by key for a plain #[Type], single and many', function () {
        $result = loaderQuery('{ reviews { id writer { name } novelsByWriter { title } } }');

        expect($result)->toBe(['data' => ['reviews' => [
            ['id' => '1', 'writer' => ['name' => 'Ann'], 'novelsByWriter' => [['title' => 'Ann 1'], ['title' => 'Ann 2']]],
            ['id' => '2', 'writer' => ['name' => 'Bob'], 'novelsByWriter' => [['title' => 'Bob 1']]],
            ['id' => '3', 'writer' => ['name' => 'Ann'], 'novelsByWriter' => [['title' => 'Ann 1'], ['title' => 'Ann 2']]],
            ['id' => '4', 'writer' => null, 'novelsByWriter' => []],
        ]]])->and(loggedQueries())->toHaveCount(2);
    });

    it('defers through Loaders inside a field method', function () {
        $result = loaderQuery('{ reviews { deferredWriter { name } } }');

        expect(array_column($result['data']['reviews'], 'deferredWriter'))->toBe([['name' => 'Ann'], ['name' => 'Bob'], ['name' => 'Ann'], null])
            ->and(loggedQueries())->toHaveCount(1);
    });

    it('shares a batch between Loaders::defer() and #[Load] with the same loader and options', function () {
        loaderQuery('{ reviews { writer { name } deferredWriter { name } } }');

        expect(loggedQueries())->toHaveCount(1);
    });

    it('runs a custom BatchedFieldDecorator through its loader once for all parents', function () {
        $result = loaderQuery('{ writers { covers } }');

        expect(array_column($result['data']['writers'], 'covers'))->toBe([['covers/Ann'], ['covers/Bob'], ['covers/Cy']])
            ->and(FileLoader::$calls)->toBe([['collection' => 'covers', 'args' => [], 'roots' => ['Ann', 'Bob', 'Cy']]]);
    });

    it('keys a batch by the field args', function () {
        $result = loaderQuery('{ writers { small: covers(size: "s") large: covers(size: "l") again: covers(size: "s") } }');

        expect($result['data']['writers'][0])->toBe(['small' => ['covers/Ann.s'], 'large' => ['covers/Ann.l'], 'again' => ['covers/Ann.s']])
            ->and(FileLoader::$calls)->toBe([
                ['collection' => 'covers', 'args' => ['size' => 's'], 'roots' => ['Ann', 'Bob', 'Cy']],
                ['collection' => 'covers', 'args' => ['size' => 'l'], 'roots' => ['Ann', 'Bob', 'Cy']],
            ]);
    });

    it('gives every execution its own Loaders', function () {
        loaderQuery('{ reviews { deferredWriter { name } } }');
        loaderQuery('{ writers { covers } }');
        Writer::create(['name' => 'Di']);
        loaderQuery('{ reviews { deferredWriter { name } } writers { covers } }');

        $registries = array_unique(array_map(spl_object_id(...), Review::$registries));

        expect($registries)->toHaveCount(2)
            ->and(array_column(FileLoader::$calls, 'roots'))->toBe([['Ann', 'Bob', 'Cy'], ['Ann', 'Bob', 'Cy', 'Di']])
            ->and(fn() => app(Loaders::class))->toThrow(LogicException::class, 'No GraphQL execution is running');
    });

    it('restores the outer Loaders after a nested execution', function () {
        $outer = null;
        $after = null;

        app(LoadersExecutionMiddleware::class)->handle('default', GraphQL::schema(), new OperationParams(BaseOperationParams::create(['query' => '{ __typename }'])), null, null, function () use (&$outer, &$after): ExecutionResult {
            $outer = app(Loaders::class);
            GraphQL::query('{ writers { covers } }');
            $after = app(Loaders::class);

            return new ExecutionResult();
        });

        expect($outer)->toBeInstanceOf(Loaders::class)
            ->and($after)->toBe($outer)
            ->and(FileLoader::$calls)->toHaveCount(1);
    });
});

describe('a batched field with #[Authorize]', function () {
    beforeEach(function () {
        $this->loadMigrationsFrom(dirname(__DIR__, 2) . '/Fixtures/RebingGraphQL/Loaders/migrations');
        seedWriters();
        FileLoader::$calls = [];
        Gate::define('seeFiles', fn(?User $user, Writer $writer) => $writer->name === 'Ann');

        schemaSdl(LoaderQueries::class, Writer::class, Novel::class, Review::class);
    });

    it('resolves a denied parent to null without handing it to the loader', function () {
        $result = loaderQuery('{ writers { name privateFiles } }');

        expect(array_column($result['data']['writers'], 'privateFiles'))->toBe([['private/Ann'], null, null])
            ->and($result)->not->toHaveKey('errors')
            ->and(FileLoader::$calls)->toBe([['collection' => 'private', 'args' => [], 'roots' => ['Ann']]]);
    });

    it('reports a denied parent as an error without handing it to the loader, in either attribute order', function (string $field) {
        $result = loaderQuery("{ writers { name $field } }");

        expect(array_column($result['data']['writers'], $field))->toBe([["$field/Ann"], null, null])
            ->and(array_column($result['errors'], 'message'))->toBe(['Forbidden', 'Forbidden'])
            ->and(FileLoader::$calls)->toBe([['collection' => $field, 'args' => [], 'roots' => ['Ann']]]);
    })->with(['#[Authorize] first' => 'guardedFirst', '#[Authorize] last' => 'guardedLast']);
});

describe('loader results', function () {
    beforeEach(fn() => schemaSdl(CrateQueries::class, Crate::class));

    it('rejects a result list of the wrong length', function () {
        $loader = WrongLengthLoader::class;

        $this->withoutExceptionHandling();

        expect(fn() => GraphQL::queryAndReturnResult('{ crates { labels } }')->toArray(DebugFlag::RETHROW_INTERNAL_EXCEPTIONS))
            ->toThrow(LogicException::class, "$loader::load() returned 1 results for 2 roots. Return a list with one result per root, in the order of the roots.");
    });

    it('fails every root of a failed batch without running the loader again', function () {
        WrongLengthLoader::$runs = 0;

        $result = GraphQL::queryAndReturnResult('{ crates { labels } }')->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE);

        expect(array_column($result['errors'], 'path'))->toBe([['crates', 0, 'labels'], ['crates', 1, 'labels']])
            ->and(WrongLengthLoader::$runs)->toBe(1);
    });

    it('reports null for a non-null field as a field error', function () {
        $result = GraphQL::queryAndReturnResult('{ crates { name label } }')->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE);

        expect($result['data'] ?? null)->toBeNull()
            ->and($result['errors'][0]['path'])->toBe(['crates', 0, 'label'])
            ->and($result['errors'][0]['extensions']['debugMessage'] ?? $result['errors'][0]['message'])
            ->toBe('Field Crate.label is non-null, but ' . NullLoader::class . ' returned null for one of its parents. Make the field nullable, or return a value for every root.');
    });

    it('allows null for a nullable field', function () {
        expect(GraphQL::query('{ crates { note } }'))->toBe(['data' => ['crates' => [['note' => null], ['note' => null]]]]);
    });
});
