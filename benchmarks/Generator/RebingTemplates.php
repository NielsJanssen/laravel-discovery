<?php

declare(strict_types=1);

namespace Benchmarks\Generator;

final class RebingTemplates
{
    /**
     * Files repeated once per unit, keyed by file name; `__I__` is the unit number.
     *
     * @return array<string, string>
     */
    public static function unit(): array
    {
        return [
            'Level__I__Type' => <<<'PHP'
                use Rebing\GraphQL\Support\EnumType;

                final class Level__I__Type extends EnumType
                {
                    protected $attributes = [
                        'name' => 'Level__I__',
                        'values' => ['Low', 'Medium', 'High'],
                    ];
                }
                PHP,
            'Item__I__Record' => <<<'PHP'
                final readonly class Item__I__Record
                {
                    /** @param list<string> $tags */
                    public function __construct(
                        public string $id,
                        public string $name,
                        public int $rank,
                        public float $weight,
                        public bool $active,
                        public string $level,
                        public array $tags,
                    ) {}
                }
                PHP,
            'Item__I__Type' => <<<'PHP'
                use GraphQL\Type\Definition\Type;
                use Rebing\GraphQL\Support\Facades\GraphQL;
                use Rebing\GraphQL\Support\Type as GraphQLType;

                final class Item__I__Type extends GraphQLType
                {
                    protected $attributes = ['name' => 'Item__I__'];

                    public function fields(): array
                    {
                        return [
                            'id' => ['type' => Type::nonNull(Type::id())],
                            'name' => ['type' => Type::nonNull(Type::string())],
                            'rank' => ['type' => Type::nonNull(Type::int())],
                            'weight' => ['type' => Type::nonNull(Type::float())],
                            'active' => ['type' => Type::nonNull(Type::boolean())],
                            'level' => ['type' => Type::nonNull(GraphQL::type('Level__I__'))],
                            'tags' => ['type' => Type::nonNull(Type::listOf(Type::nonNull(Type::string())))],
                        ];
                    }
                }
                PHP,
            'CreateItem__I__InputType' => <<<'PHP'
                use GraphQL\Type\Definition\Type;
                use Rebing\GraphQL\Support\Facades\GraphQL;
                use Rebing\GraphQL\Support\InputType;

                final class CreateItem__I__InputType extends InputType
                {
                    protected $attributes = ['name' => 'CreateItem__I__Input'];

                    public function fields(): array
                    {
                        return [
                            'name' => ['type' => Type::nonNull(Type::string()), 'rules' => ['min:2', 'max:50']],
                            'rank' => ['type' => Type::nonNull(Type::int()), 'rules' => ['integer', 'min:0']],
                            'level' => ['type' => Type::nonNull(GraphQL::type('Level__I__'))],
                            'tags' => ['type' => Type::nonNull(Type::listOf(Type::nonNull(Type::string())))],
                        ];
                    }
                }
                PHP,
            'Item__I__Query' => <<<'PHP'
                use GraphQL\Type\Definition\Type;
                use Rebing\GraphQL\Support\Facades\GraphQL;
                use Rebing\GraphQL\Support\Query;

                final class Item__I__Query extends Query
                {
                    protected $attributes = ['name' => 'item__I__'];

                    public function type(): Type
                    {
                        return Type::nonNull(GraphQL::type('Item__I__'));
                    }

                    public function args(): array
                    {
                        return ['id' => ['type' => Type::nonNull(Type::string())]];
                    }

                    /** @param array{id: string} $args */
                    public function resolve(mixed $root, array $args): Item__I__Record
                    {
                        return new Item__I__Record($args['id'], 'Item __I__', __I__, __I__.5, true, 'Medium', ['alpha', 'beta']);
                    }
                }
                PHP,
            'CreateItem__I__Mutation' => <<<'PHP'
                use GraphQL\Type\Definition\Type;
                use Rebing\GraphQL\Support\Facades\GraphQL;
                use Rebing\GraphQL\Support\Mutation;

                final class CreateItem__I__Mutation extends Mutation
                {
                    protected $attributes = ['name' => 'createItem__I__'];

                    public function type(): Type
                    {
                        return Type::nonNull(GraphQL::type('Item__I__'));
                    }

                    public function args(): array
                    {
                        return ['input' => ['type' => Type::nonNull(GraphQL::type('CreateItem__I__Input'))]];
                    }

                    /** @param array{input: array{name: string, rank: int, level: string, tags: list<string>}} $args */
                    public function resolve(mixed $root, array $args): Item__I__Record
                    {
                        $input = $args['input'];

                        return new Item__I__Record('created', $input['name'], $input['rank'], 0.5, false, $input['level'], $input['tags']);
                    }
                }
                PHP,
        ];
    }

    /**
     * Files present once at every size, keyed by file name.
     *
     * @return array<string, string>
     */
    public static function features(): array
    {
        return [
            'GreetQuery' => <<<'PHP'
                use GraphQL\Type\Definition\Type;
                use Rebing\GraphQL\Support\Query;

                final class GreetQuery extends Query
                {
                    protected $attributes = ['name' => 'greet'];

                    public function type(): Type
                    {
                        return Type::nonNull(Type::string());
                    }

                    public function args(): array
                    {
                        return ['name' => ['type' => Type::nonNull(Type::string())]];
                    }

                    /** @param array{name: string} $args */
                    public function resolve(mixed $root, array $args): string
                    {
                        return "Hello, {$args['name']}!";
                    }
                }
                PHP,
            'GenreType' => <<<'PHP'
                use Rebing\GraphQL\Support\EnumType;

                final class GenreType extends EnumType
                {
                    public const array CASES = ['Fiction', 'NonFiction', 'Poetry', 'Drama'];

                    protected $attributes = [
                        'name' => 'Genre',
                        'values' => self::CASES,
                    ];
                }
                PHP,
            'PersonRecord' => <<<'PHP'
                final readonly class PersonRecord
                {
                    public function __construct(
                        public string $name,
                        public string $country,
                    ) {}
                }
                PHP,
            'PersonType' => <<<'PHP'
                use GraphQL\Type\Definition\Type;
                use Rebing\GraphQL\Support\Type as GraphQLType;

                final class PersonType extends GraphQLType
                {
                    protected $attributes = ['name' => 'Person'];

                    public function fields(): array
                    {
                        return [
                            'name' => ['type' => Type::nonNull(Type::string())],
                            'country' => ['type' => Type::nonNull(Type::string())],
                        ];
                    }
                }
                PHP,
            'ChapterRecord' => <<<'PHP'
                final readonly class ChapterRecord
                {
                    public function __construct(
                        public int $number,
                        public string $title,
                    ) {}
                }
                PHP,
            'ChapterType' => <<<'PHP'
                use GraphQL\Type\Definition\Type;
                use Rebing\GraphQL\Support\Type as GraphQLType;

                final class ChapterType extends GraphQLType
                {
                    protected $attributes = ['name' => 'Chapter'];

                    public function fields(): array
                    {
                        return [
                            'number' => ['type' => Type::nonNull(Type::int())],
                            'title' => ['type' => Type::nonNull(Type::string())],
                        ];
                    }
                }
                PHP,
            'VolumeRecord' => <<<'PHP'
                final readonly class VolumeRecord
                {
                    /**
                     * @param list<string> $tags
                     * @param list<ChapterRecord> $chapters
                     */
                    public function __construct(
                        public string $id,
                        public string $title,
                        public int $pages,
                        public float $ratio,
                        public bool $available,
                        public string $genre,
                        public PersonRecord $author,
                        public array $tags,
                        public array $chapters,
                    ) {}
                }
                PHP,
            'VolumeType' => <<<'PHP'
                use GraphQL\Type\Definition\Type;
                use Rebing\GraphQL\Support\Facades\GraphQL;
                use Rebing\GraphQL\Support\Type as GraphQLType;

                final class VolumeType extends GraphQLType
                {
                    protected $attributes = ['name' => 'Volume'];

                    public function fields(): array
                    {
                        return [
                            'id' => ['type' => Type::nonNull(Type::id())],
                            'title' => ['type' => Type::nonNull(Type::string())],
                            'pages' => ['type' => Type::nonNull(Type::int())],
                            'ratio' => ['type' => Type::nonNull(Type::float())],
                            'available' => ['type' => Type::nonNull(Type::boolean())],
                            'genre' => ['type' => Type::nonNull(GraphQL::type('Genre'))],
                            'author' => ['type' => Type::nonNull(GraphQL::type('Person'))],
                            'tags' => ['type' => Type::nonNull(Type::listOf(Type::nonNull(Type::string())))],
                            'chapters' => ['type' => Type::nonNull(Type::listOf(Type::nonNull(GraphQL::type('Chapter'))))],
                        ];
                    }
                }
                PHP,
            'VolumesQuery' => <<<'PHP'
                use GraphQL\Type\Definition\Type;
                use Rebing\GraphQL\Support\Facades\GraphQL;
                use Rebing\GraphQL\Support\Query;

                final class VolumesQuery extends Query
                {
                    protected $attributes = ['name' => 'volumes'];

                    public function type(): Type
                    {
                        return Type::nonNull(Type::listOf(Type::nonNull(GraphQL::type('Volume'))));
                    }

                    public function args(): array
                    {
                        return ['count' => ['type' => Type::nonNull(Type::int())]];
                    }

                    /**
                     * @param array{count: int} $args
                     * @return list<VolumeRecord>
                     */
                    public function resolve(mixed $root, array $args): array
                    {
                        $volumes = [];

                        for ($n = 1; $n <= $args['count']; $n++) {
                            $volumes[] = new VolumeRecord(
                                "v{$n}",
                                "Volume {$n}",
                                100 + $n,
                                $n / 4,
                                $n % 2 === 0,
                                GenreType::CASES[$n % 4],
                                new PersonRecord('Author ' . ($n % 10), 'NL'),
                                ['acme', 't' . ($n % 5)],
                                [new ChapterRecord(1, 'One'), new ChapterRecord(2, 'Two'), new ChapterRecord(3, 'Three')],
                            );
                        }

                        return $volumes;
                    }
                }
                PHP,
            'SearchQuery' => <<<'PHP'
                use GraphQL\Type\Definition\Type;
                use Rebing\GraphQL\Support\Query;

                final class SearchQuery extends Query
                {
                    protected $attributes = ['name' => 'search'];

                    public function type(): Type
                    {
                        return Type::nonNull(Type::listOf(Type::nonNull(Type::string())));
                    }

                    public function args(): array
                    {
                        return [
                            'term' => ['type' => Type::nonNull(Type::string()), 'rules' => ['min:3', 'max:40']],
                            'limit' => ['type' => Type::nonNull(Type::int()), 'rules' => ['integer', 'between:1,50']],
                        ];
                    }

                    /**
                     * @param array{term: string, limit: int} $args
                     * @return list<string>
                     */
                    public function resolve(mixed $root, array $args): array
                    {
                        $term = $args['term'];

                        return array_map(static fn(int $n): string => "{$term} {$n}", range(1, $args['limit']));
                    }
                }
                PHP,
            'GenresQuery' => <<<'PHP'
                use GraphQL\Type\Definition\Type;
                use Rebing\GraphQL\Support\Facades\GraphQL;
                use Rebing\GraphQL\Support\Query;

                final class GenresQuery extends Query
                {
                    protected $attributes = ['name' => 'genres'];

                    public function type(): Type
                    {
                        return Type::nonNull(Type::listOf(Type::nonNull(GraphQL::type('Genre'))));
                    }

                    public function args(): array
                    {
                        return [
                            'after' => ['type' => Type::nonNull(GraphQL::type('Genre'))],
                            'count' => ['type' => Type::nonNull(Type::int())],
                        ];
                    }

                    /**
                     * @param array{after: string, count: int} $args
                     * @return list<string>
                     */
                    public function resolve(mixed $root, array $args): array
                    {
                        $offset = (int) array_search($args['after'], GenreType::CASES, true);
                        $genres = [];

                        for ($n = 1; $n <= $args['count']; $n++) {
                            $genres[] = GenreType::CASES[($offset + $n) % 4];
                        }

                        return $genres;
                    }
                }
                PHP,
            'NewPersonInputType' => <<<'PHP'
                use GraphQL\Type\Definition\Type;
                use Rebing\GraphQL\Support\InputType;

                final class NewPersonInputType extends InputType
                {
                    protected $attributes = ['name' => 'NewPersonInput'];

                    public function fields(): array
                    {
                        return [
                            'name' => ['type' => Type::nonNull(Type::string()), 'rules' => ['min:2', 'max:60']],
                            'country' => ['type' => Type::nonNull(Type::string()), 'rules' => ['size:2']],
                        ];
                    }
                }
                PHP,
            'NewVolumeInputType' => <<<'PHP'
                use GraphQL\Type\Definition\Type;
                use Rebing\GraphQL\Support\Facades\GraphQL;
                use Rebing\GraphQL\Support\InputType;

                final class NewVolumeInputType extends InputType
                {
                    protected $attributes = ['name' => 'NewVolumeInput'];

                    public function fields(): array
                    {
                        return [
                            'title' => ['type' => Type::nonNull(Type::string()), 'rules' => ['min:2', 'max:120']],
                            'pages' => ['type' => Type::nonNull(Type::int()), 'rules' => ['integer', 'min:1', 'max:5000']],
                            'genre' => ['type' => Type::nonNull(GraphQL::type('Genre'))],
                            'author' => ['type' => Type::nonNull(GraphQL::type('NewPersonInput'))],
                            'tags' => ['type' => Type::nonNull(Type::listOf(Type::nonNull(Type::string())))],
                        ];
                    }
                }
                PHP,
            'CreateVolumeMutation' => <<<'PHP'
                use GraphQL\Type\Definition\Type;
                use Rebing\GraphQL\Support\Facades\GraphQL;
                use Rebing\GraphQL\Support\Mutation;

                final class CreateVolumeMutation extends Mutation
                {
                    protected $attributes = ['name' => 'createVolume'];

                    public function type(): Type
                    {
                        return Type::nonNull(GraphQL::type('Volume'));
                    }

                    public function args(): array
                    {
                        return ['input' => ['type' => Type::nonNull(GraphQL::type('NewVolumeInput'))]];
                    }

                    /** @param array{input: array{title: string, pages: int, genre: string, author: array{name: string, country: string}, tags: list<string>}} $args */
                    public function resolve(mixed $root, array $args): VolumeRecord
                    {
                        $input = $args['input'];

                        return new VolumeRecord(
                            'created',
                            $input['title'],
                            $input['pages'],
                            $input['pages'] / 100,
                            true,
                            $input['genre'],
                            new PersonRecord($input['author']['name'], $input['author']['country']),
                            $input['tags'],
                            [],
                        );
                    }
                }
                PHP,
            'AccountRecord' => <<<'PHP'
                final readonly class AccountRecord
                {
                    public function __construct(
                        public int $number,
                        public string $holder,
                        public int $balance,
                    ) {}
                }
                PHP,
            'AccountType' => <<<'PHP'
                use GraphQL\Type\Definition\Type;
                use Illuminate\Support\Facades\Gate;
                use Rebing\GraphQL\Support\Type as GraphQLType;

                final class AccountType extends GraphQLType
                {
                    protected $attributes = ['name' => 'Account'];

                    public function fields(): array
                    {
                        return [
                            'number' => ['type' => Type::nonNull(Type::int())],
                            'holder' => ['type' => Type::nonNull(Type::string())],
                            'balance' => [
                                'type' => Type::int(),
                                'privacy' => static fn(AccountRecord $root): bool => Gate::allows('viewBalance', $root),
                            ],
                        ];
                    }
                }
                PHP,
            'AccountsQuery' => <<<'PHP'
                use GraphQL\Type\Definition\ResolveInfo;
                use GraphQL\Type\Definition\Type;
                use Illuminate\Support\Facades\Auth;
                use Rebing\GraphQL\Support\Facades\GraphQL;
                use Rebing\GraphQL\Support\Query;

                final class AccountsQuery extends Query
                {
                    protected $attributes = ['name' => 'accounts'];

                    public function authorize(mixed $root, array $args, mixed $ctx, ?ResolveInfo $resolveInfo = null): bool
                    {
                        return Auth::check();
                    }

                    public function type(): Type
                    {
                        return Type::nonNull(Type::listOf(Type::nonNull(GraphQL::type('Account'))));
                    }

                    public function args(): array
                    {
                        return ['count' => ['type' => Type::nonNull(Type::int())]];
                    }

                    /**
                     * @param array{count: int} $args
                     * @return list<AccountRecord>
                     */
                    public function resolve(mixed $root, array $args): array
                    {
                        $accounts = [];

                        for ($n = 1; $n <= $args['count']; $n++) {
                            $accounts[] = new AccountRecord($n, "Holder {$n}", $n * 100);
                        }

                        return $accounts;
                    }
                }
                PHP,
            'Author' => <<<'PHP'
                use Illuminate\Database\Eloquent\Model;
                use Illuminate\Database\Eloquent\Relations\HasMany;

                class Author extends Model
                {
                    public $timestamps = false;

                    protected $table = 'bench_authors';

                    protected $guarded = [];

                    /** @return HasMany<Book, $this> */
                    public function books(): HasMany
                    {
                        return $this->hasMany(Book::class)->orderBy('id');
                    }
                }
                PHP,
            'Book' => <<<'PHP'
                use Illuminate\Database\Eloquent\Model;

                class Book extends Model
                {
                    public $timestamps = false;

                    protected $table = 'bench_books';

                    protected $guarded = [];
                }
                PHP,
            'AuthorType' => <<<'PHP'
                use GraphQL\Type\Definition\Type;
                use Rebing\GraphQL\Support\Facades\GraphQL;
                use Rebing\GraphQL\Support\Type as GraphQLType;

                final class AuthorType extends GraphQLType
                {
                    protected $attributes = ['name' => 'Author'];

                    public function fields(): array
                    {
                        return [
                            'id' => ['type' => Type::nonNull(Type::id())],
                            'name' => ['type' => Type::nonNull(Type::string())],
                            'books' => ['type' => Type::nonNull(Type::listOf(Type::nonNull(GraphQL::type('Book'))))],
                        ];
                    }
                }
                PHP,
            'BookType' => <<<'PHP'
                use GraphQL\Type\Definition\Type;
                use Rebing\GraphQL\Support\Type as GraphQLType;

                final class BookType extends GraphQLType
                {
                    protected $attributes = ['name' => 'Book'];

                    public function fields(): array
                    {
                        return [
                            'id' => ['type' => Type::nonNull(Type::id())],
                            'title' => ['type' => Type::nonNull(Type::string())],
                            'pages' => ['type' => Type::nonNull(Type::int())],
                        ];
                    }
                }
                PHP,
            'AuthorsQuery' => <<<'PHP'
                use GraphQL\Type\Definition\Type;
                use Rebing\GraphQL\Support\Facades\GraphQL;
                use Rebing\GraphQL\Support\Query;

                final class AuthorsQuery extends Query
                {
                    protected $attributes = ['name' => 'authors'];

                    public function type(): Type
                    {
                        return Type::nonNull(Type::listOf(Type::nonNull(GraphQL::type('Author'))));
                    }

                    /** @return list<Author> */
                    public function resolve(): array
                    {
                        return Author::query()->orderBy('id')->get()->all();
                    }
                }
                PHP,
            'EagerAuthorsQuery' => <<<'PHP'
                use GraphQL\Type\Definition\Type;
                use Rebing\GraphQL\Support\Facades\GraphQL;
                use Rebing\GraphQL\Support\Query;

                final class EagerAuthorsQuery extends Query
                {
                    protected $attributes = ['name' => 'authors'];

                    public function type(): Type
                    {
                        return Type::nonNull(Type::listOf(Type::nonNull(GraphQL::type('Author'))));
                    }

                    /** @return list<Author> */
                    public function resolve(): array
                    {
                        return Author::query()->with('books')->orderBy('id')->get()->all();
                    }
                }
                PHP,
        ];
    }

    /**
     * Rebing's registration for a size, as `graphql.types` and the default schema's operations.
     *
     * @return array{types: array<string, string>, query: array<string, string>, mutation: array<string, string>, eager: array<string, string>}
     */
    public static function registration(string $namespace, int $units): array
    {
        $types = [];
        $query = [];
        $mutation = [];

        for ($i = 1; $i <= $units; $i++) {
            $types["Level{$i}"] = "{$namespace}\\Level{$i}Type";
            $types["Item{$i}"] = "{$namespace}\\Item{$i}Type";
            $types["CreateItem{$i}Input"] = "{$namespace}\\CreateItem{$i}InputType";
            $query["item{$i}"] = "{$namespace}\\Item{$i}Query";
            $mutation["createItem{$i}"] = "{$namespace}\\CreateItem{$i}Mutation";
        }

        foreach (['Genre', 'Person', 'Chapter', 'Volume', 'Account', 'Author', 'Book'] as $type) {
            $types[$type] = "{$namespace}\\{$type}Type";
        }

        $types['NewPersonInput'] = "{$namespace}\\NewPersonInputType";
        $types['NewVolumeInput'] = "{$namespace}\\NewVolumeInputType";

        foreach (['greet' => 'GreetQuery', 'volumes' => 'VolumesQuery', 'search' => 'SearchQuery', 'genres' => 'GenresQuery', 'accounts' => 'AccountsQuery', 'authors' => 'AuthorsQuery'] as $name => $class) {
            $query[$name] = "{$namespace}\\{$class}";
        }

        $mutation['createVolume'] = "{$namespace}\\CreateVolumeMutation";

        return [
            'types' => $types,
            'query' => $query,
            'mutation' => $mutation,
            'eager' => ['authors' => "{$namespace}\\EagerAuthorsQuery"],
        ];
    }
}
