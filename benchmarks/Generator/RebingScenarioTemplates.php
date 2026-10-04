<?php

declare(strict_types=1);

namespace Benchmarks\Generator;

final class RebingScenarioTemplates
{
    /**
     * The scenario classes present once at every size, keyed by file name.
     *
     * @return array<string, string>
     */
    public static function features(): array
    {
        return [
            'AuthorQuery' => <<<'PHP'
                use GraphQL\Type\Definition\Type;
                use Rebing\GraphQL\Support\Facades\GraphQL;
                use Rebing\GraphQL\Support\Query;

                final class AuthorQuery extends Query
                {
                    protected $attributes = ['name' => 'author'];

                    public function type(): Type
                    {
                        return Type::nonNull(GraphQL::type('Author'));
                    }

                    public function args(): array
                    {
                        return ['id' => ['type' => Type::nonNull(Type::id()), 'rules' => ['exists:bench_authors,id']]];
                    }

                    /** @param array{id: string} $args */
                    public function resolve(mixed $root, array $args): Author
                    {
                        return Author::query()->where('id', $args['id'])->firstOrFail();
                    }
                }
                PHP,
            'RenameVolumeMutation' => <<<'PHP'
                use GraphQL\Type\Definition\Type;
                use Rebing\GraphQL\Support\Mutation;

                final class RenameVolumeMutation extends Mutation
                {
                    protected $attributes = ['name' => 'renameVolume'];

                    public function type(): Type
                    {
                        return Type::nonNull(Type::string());
                    }

                    public function args(): array
                    {
                        return [
                            'title' => ['type' => Type::nonNull(Type::string()), 'rules' => ['min:3', 'max:20']],
                            'edition' => ['type' => Type::nonNull(Type::int()), 'rules' => ['integer', 'between:1,100']],
                        ];
                    }

                    /** @param array{title: string, edition: int} $args */
                    public function resolve(mixed $root, array $args): string
                    {
                        return "{$args['title']} #{$args['edition']}";
                    }
                }
                PHP,
            'BookPageQuery' => <<<'PHP'
                use GraphQL\Type\Definition\Type;
                use Illuminate\Contracts\Pagination\LengthAwarePaginator;
                use Rebing\GraphQL\Support\Facades\GraphQL;
                use Rebing\GraphQL\Support\Query;

                final class BookPageQuery extends Query
                {
                    protected $attributes = ['name' => 'bookPage'];

                    public function type(): Type
                    {
                        return Type::nonNull(GraphQL::paginate('Book'));
                    }

                    public function args(): array
                    {
                        return [
                            'page' => ['type' => Type::int(), 'defaultValue' => 1, 'rules' => ['integer', 'min:1']],
                            'limit' => ['type' => Type::int(), 'defaultValue' => 20, 'rules' => ['integer', 'min:1']],
                        ];
                    }

                    /**
                     * @param array{page: int, limit: int} $args
                     * @return LengthAwarePaginator<int, Book>
                     */
                    public function resolve(mixed $root, array $args): LengthAwarePaginator
                    {
                        return Book::query()->orderBy('id')->paginate($args['limit'], ['*'], 'page', $args['page']);
                    }
                }
                PHP,
            'ShoutMiddleware' => <<<'PHP'
                use Closure;
                use GraphQL\Type\Definition\ResolveInfo;
                use Rebing\GraphQL\Support\Middleware;

                final class ShoutMiddleware extends Middleware
                {
                    public function handle(mixed $root, array $args, mixed $context, ResolveInfo $info, Closure $next): mixed
                    {
                        $result = $next($root, $args, $context, $info);

                        return is_string($result) ? strtoupper($result) : $result;
                    }
                }
                PHP,
            'ShoutQuery' => <<<'PHP'
                use GraphQL\Type\Definition\Type;
                use Rebing\GraphQL\Support\Query;

                final class ShoutQuery extends Query
                {
                    protected $attributes = ['name' => 'shout'];

                    protected $middleware = [ShoutMiddleware::class];

                    public function type(): Type
                    {
                        return Type::nonNull(Type::string());
                    }

                    public function args(): array
                    {
                        return ['text' => ['type' => Type::nonNull(Type::string())]];
                    }

                    /** @param array{text: string} $args */
                    public function resolve(mixed $root, array $args): string
                    {
                        return "{$args['text']}!";
                    }
                }
                PHP,
            'VaultQuery' => <<<'PHP'
                use GraphQL\Type\Definition\Type;
                use Illuminate\Support\Facades\Gate;
                use Rebing\GraphQL\Error\AuthorizationError;
                use Rebing\GraphQL\Support\Query;

                final class VaultQuery extends Query
                {
                    protected $attributes = ['name' => 'vault'];

                    public function type(): Type
                    {
                        return Type::nonNull(Type::string());
                    }

                    public function args(): array
                    {
                        return ['number' => ['type' => Type::nonNull(Type::int())]];
                    }

                    /** @param array{number: int} $args */
                    public function resolve(mixed $root, array $args): string
                    {
                        if (Gate::denies('openVault', $args['number'])) {
                            throw new AuthorizationError('Forbidden');
                        }

                        return "Vault {$args['number']} is open";
                    }
                }
                PHP,
            'GadgetRecord' => <<<'PHP'
                final readonly class GadgetRecord
                {
                    /** @param array<string, int> $specs */
                    public function __construct(
                        public string $name,
                        public array $specs = [],
                    ) {}
                }
                PHP,
            'GadgetType' => <<<'PHP'
                use GraphQL\Type\Definition\Type;
                use Rebing\GraphQL\Support\Type as GraphQLType;

                final class GadgetType extends GraphQLType
                {
                    protected $attributes = ['name' => 'Gadget'];

                    public function fields(): array
                    {
                        $fields = ['name' => ['type' => Type::nonNull(Type::string())]];

                        foreach (['width', 'height', 'depth', 'weight'] as $key) {
                            $fields[$key] = ['type' => Type::int(), 'resolve' => static fn(GadgetRecord $root): ?int => $root->specs[$key] ?? null];
                        }

                        return $fields;
                    }
                }
                PHP,
            'GadgetsQuery' => <<<'PHP'
                use GraphQL\Type\Definition\Type;
                use Rebing\GraphQL\Support\Facades\GraphQL;
                use Rebing\GraphQL\Support\Query;

                final class GadgetsQuery extends Query
                {
                    protected $attributes = ['name' => 'gadgets'];

                    public function type(): Type
                    {
                        return Type::nonNull(Type::listOf(Type::nonNull(GraphQL::type('Gadget'))));
                    }

                    public function args(): array
                    {
                        return ['count' => ['type' => Type::nonNull(Type::int())]];
                    }

                    /**
                     * @param array{count: int} $args
                     * @return list<GadgetRecord>
                     */
                    public function resolve(mixed $root, array $args): array
                    {
                        $gadgets = [];

                        for ($n = 1; $n <= $args['count']; $n++) {
                            $specs = ['width' => $n, 'height' => $n * 2, 'weight' => $n * 10];

                            if ($n % 3 !== 0) {
                                $specs['depth'] = $n + 1;
                            }

                            $gadgets[] = new GadgetRecord("Gadget {$n}", $specs);
                        }

                        return $gadgets;
                    }
                }
                PHP,
            'ReadingType' => <<<'PHP'
                use GraphQL\Type\Definition\Type;
                use Rebing\GraphQL\Support\Type as GraphQLType;

                final class ReadingType extends GraphQLType
                {
                    protected $attributes = ['name' => 'Reading'];

                    public function fields(): array
                    {
                        return [
                            'sensor' => ['type' => Type::nonNull(Type::string())],
                            'value' => ['type' => Type::nonNull(Type::float())],
                            'unit' => ['type' => Type::string()],
                        ];
                    }
                }
                PHP,
            'ReadingsQuery' => <<<'PHP'
                use GraphQL\Type\Definition\Type;
                use Rebing\GraphQL\Support\Facades\GraphQL;
                use Rebing\GraphQL\Support\Query;

                final class ReadingsQuery extends Query
                {
                    protected $attributes = ['name' => 'readings'];

                    public function type(): Type
                    {
                        return Type::nonNull(Type::listOf(Type::nonNull(GraphQL::type('Reading'))));
                    }

                    public function args(): array
                    {
                        return ['count' => ['type' => Type::nonNull(Type::int())]];
                    }

                    /**
                     * @param array{count: int} $args
                     * @return list<array{sensor: string, value: float, unit: ?string}>
                     */
                    public function resolve(mixed $root, array $args): array
                    {
                        $readings = [];

                        for ($n = 1; $n <= $args['count']; $n++) {
                            $readings[] = ['sensor' => "S{$n}", 'value' => $n / 8, 'unit' => $n % 2 === 0 ? 'kPa' : null];
                        }

                        return $readings;
                    }
                }
                PHP,
        ];
    }

    /**
     * Rebing's registration of the scenario types and operations.
     *
     * @return array{types: array<string, string>, query: array<string, string>, mutation: array<string, string>}
     */
    public static function registration(string $namespace): array
    {
        return [
            'types' => ['Gadget' => "{$namespace}\\GadgetType", 'Reading' => "{$namespace}\\ReadingType"],
            'query' => [
                'author' => "{$namespace}\\AuthorQuery",
                'bookPage' => "{$namespace}\\BookPageQuery",
                'shout' => "{$namespace}\\ShoutQuery",
                'vault' => "{$namespace}\\VaultQuery",
                'gadgets' => "{$namespace}\\GadgetsQuery",
                'readings' => "{$namespace}\\ReadingsQuery",
            ],
            'mutation' => ['renameVolume' => "{$namespace}\\RenameVolumeMutation"],
        ];
    }
}
