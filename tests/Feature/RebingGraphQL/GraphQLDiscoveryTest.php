<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use GraphQL\Language\AST\StringValueNode;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredAction;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredField;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\NullType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRef;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Tests\Fixtures\RebingGraphQL\AlwaysAllowGate;
use Tests\Fixtures\RebingGraphQL\AlwaysDenyGate;
use Tests\Fixtures\RebingGraphQL\AuthorizedQuery;
use Tests\Fixtures\RebingGraphQL\DeprecatedQueries;
use Tests\Fixtures\RebingGraphQL\DescribedQuery;
use Tests\Fixtures\RebingGraphQL\ExplicitTypeArgQuery;
use Tests\Fixtures\RebingGraphQL\ExplicitTypeReturnQuery;
use Tests\Fixtures\RebingGraphQL\GatedQuery;
use Tests\Fixtures\RebingGraphQL\MiddlewareQuery;
use Tests\Fixtures\RebingGraphQL\ScalarActions;
use Tests\Fixtures\RebingGraphQL\SchemaOnMethodQuery;
use Tests\Fixtures\RebingGraphQL\SchemaQueries;
use Workbench\App\GraphQL\Middleware\ExclamationMiddleware;
use Workbench\App\GraphQL\Middleware\UppercaseMiddleware;
use Workbench\App\GraphQL\Mutations\RebingNativeMutation;
use Workbench\App\Models\User;

describe('resolution and return-type inference', function () {
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

    it('infers the GraphQL type and nullability from the method return type', function () {
        $actions = discoveredActions(ScalarActions::class);

        expect(array_map(fn(DiscoveredAction $a) => [$a->action->type, $a->action->nullable], $actions))->toBe([
            'greet' => ['string', false],
            'maybeGreet' => ['string', true],
            'isReady' => ['bool', false],
            'percent' => ['float', false],
            'doNothing' => ['void', true],
            'add' => ['int', false],
        ]);
    });

    it('prints the scalar actions in the schema, mapping void to the Null scalar', function () {
        expect(schemaSdl(ScalarActions::class))->toBe(<<<'SDL'
            type Query {
              greet(name: String!): String!
              maybeGreet: String
              isReady: Boolean!
              percent(enabled: Boolean!, threshold: Float!): Float!
              doNothing: Null
              add(a: Int, b: Int): Int!
            }

            "Represents the absence of a return value."
            scalar Null

            SDL);
    });

    it('stores default values and widens nullable for optional arguments during discovery', function () {
        [$a, $b] = discoveredActions(ScalarActions::class)['add']->args;

        expect($a->type->nullable)->toBeTrue()
            ->and($a->hasDefault)->toBeTrue()
            ->and($a->defaultValue)->toBe(0)
            ->and($b->hasDefault)->toBeTrue()
            ->and($b->defaultValue)->toBe(0);
    });

    it('rejects an action shape at discovery', function (object $shape, string $format, string $exception) {
        expectRejected($shape, $format, exception: $exception);
    })->with([
        'a non-scalar argument without #[Arg]' => [
            fn() => new class {
                #[Query(type: 'Book', list: true)]
                public function resolve(array $missingArg): array
                {
                    return [];
                }
            },
            'Parameter $missingArg in %1$s::resolve is not a scalar or enum type. Use #[Arg(type: \'GraphQLTypeName\')] to specify the GraphQL type.',
            \RuntimeException::class,
        ],
        'an #[Arg(name:)] colliding with another parameter name' => [
            fn() => new class {
                #[Query]
                public function resolve(#[Arg('name')] string $title, string $name): string
                {
                    return "{$title} {$name}";
                }
            },
            'in %1$s::resolve takes the arg "name", which collides with the arg of the parameter $title. Rename one with #[Arg(name: ...)].',
            \LogicException::class,
        ],
        'no type and a non-scalar return type' => [
            fn() => new class {
                #[Query]
                public function resolve(): array
                {
                    return [];
                }
            },
            'Method %1$s::resolve has type array, which needs #[Query(of: ...)] to name the type of its items, or #[Query(type: ...)] to name its GraphQL type. A scalar, void, enum or #[Type] class return type is inferred.',
            \RuntimeException::class,
        ],
    ]);

    it('resolves a query with optional args', function (string $query, int $expected) {
        $this->postJson('/graphql', ['query' => $query])
            ->assertOk()
            ->assertJsonPath('data.add', $expected);
    })->with([
        'using defaults when omitted' => ['{ add }', 0],
        'when values are provided' => ['{ add(a: 3, b: 4) }', 7],
        'using the default when null is passed' => ['{ add(a: 5, b: null) }', 5],
    ]);

    it('resolves a void mutation returning null', function () {
        $this->postJson('/graphql', [
            'query' => 'mutation { clearCache }',
        ])
            ->assertOk()
            ->assertJsonPath('data.clearCache', null);
    });

    it('runs #[Arg(rules:)] validation and rejects requests with invalid args', function () {
        $this->postJson('/graphql', ['query' => '{ validatedHello(name: "ab") }'])
            ->assertOk()
            ->assertJsonPath('data.validatedHello', null)
            ->assertJsonPath('errors.0.extensions.validation.name.0', 'The name field must be at least 3 characters.');
    });

    it('resolves the query when #[Arg(rules:)] validation passes', function () {
        $this->postJson('/graphql', ['query' => '{ validatedHello(name: "Niels") }'])
            ->assertOk()
            ->assertJsonPath('data.validatedHello', 'hi, Niels');
    });

    it('honours #[Arg(type: ...)] as an explicit GraphQL type override', function () {
        $item = discoveredActions(ExplicitTypeArgQuery::class)['resolve'];

        expect($item->args[0]->type)->toEqual(TypeRef::named('CustomFilter'));
    });
});

describe('class-based registrations', function () {
    it('registers classes that extend Rebing\'s Mutation as discovered mutation fields', function () {
        $items = iterator_to_array(discoverGraphQL(RebingNativeMutation::class)->getItems());

        expect($items)->toHaveCount(1)
            ->and($items[0])->toBeInstanceOf(DiscoveredField::class)
            ->and($items[0]->fieldType)->toBe('mutation')
            ->and($items[0]->class)->toBe(RebingNativeMutation::class);
    });
});

describe('null type', function () {
    it('serializes to null and refuses to parse input', function () {
        $type = new NullType();

        expect($type->serialize('anything'))->toBeNull()
            ->and(fn() => $type->parseValue('x'))
                ->toThrow(\RuntimeException::class, 'cannot be used as an input argument')
            ->and(fn() => $type->parseLiteral(new StringValueNode(['value' => 'x'])))
                ->toThrow(\RuntimeException::class, 'cannot be used as an input argument')
            ->and($type->toType())->toBeInstanceOf(NullType::class);
    });
});

describe('schema routing', function () {
    it('resolves the schema of every action from #[Schema] decorators and the explicit schema argument', function () {
        $actions = [...discoveredActions(SchemaOnMethodQuery::class), ...discoveredActions(SchemaQueries::class)];

        expect(array_column(array_map(
            fn(DiscoveredAction $a) => ['name' => $a->action->name, 'schema' => $a->action->schema],
            $actions,
        ), 'schema', 'name'))->toBe([
            'methodLevel' => 'admin',
            'classLevelQuery' => 'admin',
            'classLevelMutation' => 'admin',
            'methodWins' => 'public',
            'explicitWins' => 'reports',
        ]);
    });

    it('routes decorated actions to their declared schema in graphql.schemas config', function () {
        config()->set('graphql.schemas', []);

        $discovery = discoverGraphQL(SchemaOnMethodQuery::class, SchemaQueries::class);
        $discovery->apply();

        $schemas = config('graphql.schemas');

        expect($schemas)->toHaveKey('admin')
            ->and($schemas['admin']['query'])->toHaveKey('methodLevel')
            ->and($schemas['admin']['query'])->toHaveKey('classLevelQuery')
            ->and($schemas['admin']['mutation'])->toHaveKey('classLevelMutation')
            ->and($schemas['public']['query'])->toHaveKey('methodWins')
            ->and($schemas['reports']['query'])->toHaveKey('explicitWins')
            ->and($schemas['default'] ?? [])->not->toHaveKey('query');
    });

    it('routes undecorated actions to graphql.default_schema', function (?string $configured, string $expected) {
        config()->set('graphql.schemas', []);

        if ($configured !== null) {
            config()->set('graphql.default_schema', $configured);
        }

        expect(config('graphql.default_schema'))->toBe($expected);

        discoverGraphQL(ScalarActions::class)->apply();

        $schemas = config('graphql.schemas');

        expect($schemas)->toHaveKey($expected)
            ->and($schemas[$expected]['query'] ?? [])->not->toBeEmpty()
            ->and($schemas)->toHaveCount(1);
    })->with([
        'the Rebing default when unchanged' => [null, 'default'],
        'the configured schema' => ['custom', 'custom'],
    ]);

    it('keeps decorated actions in their declared schema even when graphql.default_schema is set', function () {
        config()->set('graphql.schemas', []);
        config()->set('graphql.default_schema', 'custom');

        $discovery = discoverGraphQL(SchemaOnMethodQuery::class, ScalarActions::class);
        $discovery->apply();

        $schemas = config('graphql.schemas');

        expect($schemas['admin']['query'])->toHaveKey('methodLevel')
            ->and($schemas['custom']['query'] ?? [])->not->toBeEmpty()
            ->and($schemas['custom']['query'] ?? [])->not->toHaveKey('methodLevel');
    });
});

describe('parameter injections', function () {
    it('passes ResolveInfo into resolve() with the actual field name at runtime', function () {
        auth()->logout();

        $this->postJson('/graphql', ['query' => '{ whoami }'])
            ->assertOk()
            ->assertJsonPath('data.whoami', 'field=whoami context=guest');
    });

    it('passes the authenticated user as Context into resolve() via Rebing AddAuthUserContextValueMiddleware', function () {
        $this->actingAs(new User());

        $this->postJson('/graphql', ['query' => '{ whoami }'])
            ->assertOk()
            ->assertJsonPath('data.whoami', 'field=whoami context=user');
    });
});

describe('description', function () {
    it('exposes #[Query(description: ...)] on the discovered action and the Field attributes', function () {
        $item = discoveredActions(DescribedQuery::class)['resolve'];

        $field = $item->createType(app());

        expect($item->action->description)->toBe('Returns a greeting')
            ->and($field->attributes())->toMatchArray([
                'name' => 'described',
                'description' => 'Returns a greeting',
            ]);
    });
});

describe('deprecation', function () {
    it('maps native #[\Deprecated] on methods and parameters to GraphQL deprecationReason', function () {
        $item = discoveredActions(DeprecatedQueries::class)['withMessage'];

        $field = $item->createType(app());

        expect($item->deprecationReason)->toBe('Use newGreet instead (since 2.0.0)')
            ->and($item->args[0]->deprecationReason)->toBe('Pass name via context')
            ->and($field->attributes())->toHaveKey('deprecationReason', 'Use newGreet instead (since 2.0.0)')
            ->and($field->args()['name'])->toHaveKey('deprecationReason', 'Pass name via context');
    });

    it('words a #[\Deprecated] without a message', function (string $method, string $reason) {
        expect(discoveredActions(DeprecatedQueries::class)[$method]->deprecationReason)->toBe($reason);
    })->with([
        'since only' => ['sinceOnly', 'Deprecated since 3.0.0'],
        'bare' => ['bare', 'Deprecated'],
    ]);
});

describe('middleware', function () {
    it('collects class-level then method-level middleware in execution order', function () {
        $actions = discoveredActions(MiddlewareQuery::class);

        expect($actions['resolve']->middleware)->toBe([
            ExclamationMiddleware::class,
            UppercaseMiddleware::class,
        ])
            ->and($actions['whisper']->middleware)->toBe([
                ExclamationMiddleware::class,
            ]);
    });

    it('runs discovered middleware around the resolver in class-then-method order over a real GraphQL request', function () {
        $this->postJson('/graphql', [
            'query' => '{ shout(name: "world") }',
        ])
            ->assertOk()
            ->assertJsonPath('data.shout', 'HELLO WORLD!');
    });
});

describe('authorization', function () {
    it('collects #[Authorize] attributes from class and method, class-first', function () {
        $actions = discoveredActions(GatedQuery::class);

        expect(array_map(fn($a) => $a->gate, $actions['denied']->authorizations))
            ->toBe([AlwaysAllowGate::class, AlwaysDenyGate::class])
            ->and(array_map(fn($a) => $a->gate, $actions['allowed']->authorizations))
            ->toBe([AlwaysAllowGate::class]);
    });

    it('requires an authenticated user for a bare #[Authorize]', function (bool $loggedIn, bool $allowed) {
        $loggedIn ? $this->actingAs(new User()) : auth()->logout();

        $field = discoveredActions(AuthorizedQuery::class)['resolve']->createType(app());

        expect($field->authorize(null, [], null, null))->toBe($allowed);

        if (! $allowed) {
            expect($field->getAuthorizationMessage())->toBe('Authentication required');
        }
    })->with([
        'rejects when no user is authenticated' => [false, false],
        'accepts when a user is authenticated' => [true, true],
    ]);

    it('delegates to the configured gate when #[Authorize(gate: ...)] is used', function () {
        $actions = discoveredActions(GatedQuery::class);

        $denied = $actions['denied']->createType(app());
        $allowed = $actions['allowed']->createType(app());

        expect($denied->authorize(null, [], null, null))->toBeFalse()
            ->and($denied->getAuthorizationMessage())->toBe('denied by gate')
            ->and($allowed->authorize(null, [], null, null))->toBeTrue();
    });

    it('guards a #[Authorize] query over the GraphQL endpoint', function (bool $loggedIn) {
        $loggedIn ? $this->actingAs(new User()) : auth()->logout();

        $response = $this->postJson('/graphql', ['query' => '{ secret }'])->assertOk();

        if ($loggedIn) {
            $response->assertJsonPath('data.secret', 'top-secret');
        } else {
            $response->assertJsonPath('data.secret', null)
                ->assertJsonPath('errors.0.message', 'Unauthorized');
        }
    })->with([
        'unauthenticated' => [false],
        'logged in' => [true],
    ]);
});

describe('nullability alongside an explicit type', function () {
    it('sets the action nullability from the return type and nullable:', function (string $method, bool $nullable) {
        expect(discoveredActions(ExplicitTypeReturnQuery::class)[$method]->action->nullable)->toBe($nullable);
    })->with([
        'a nullable return widens an explicitly typed field' => ['nullableReturn', true],
        'a non-nullable return stays non-null' => ['nonNullableReturn', false],
        'nullable: true survives a non-nullable return' => ['explicitlyNullable', true],
        'a nullable list return makes the list itself nullable' => ['nullableList', true],
        'an undeclared return type stays non-null' => ['undeclaredReturn', false],
    ]);
});

describe('nullable explicit type end-to-end', function () {
    it('renders the field as nullable in the built schema', function () {
        $field = GraphQL::schema('default')->getQueryType()->getField('maybeBook');

        expect((string) $field->getType())->toBe('Book');
    });

    it('resolves to null without an error', function () {
        $this->postJson('/graphql', ['query' => '{ maybeBook { title } }'])
            ->assertOk()
            ->assertJsonPath('data.maybeBook', null)
            ->assertJsonMissingPath('errors');
    });

    it('resolves the object when there is one', function () {
        $this->postJson('/graphql', ['query' => '{ maybeBook(found: true) { title } }'])
            ->assertOk()
            ->assertJsonPath('data.maybeBook.title', 'De Avonden');
    });
});
