<?php

declare(strict_types=1);

namespace Benchmarks\Generator;

final class DiscoveryTemplates
{
    /**
     * Files repeated once per unit, keyed by file name; `__I__` is the unit number.
     *
     * @return array<string, string>
     */
    public static function unit(): array
    {
        return [
            'Level__I__' => <<<'PHP'
                enum Level__I__
                {
                    case Low;
                    case Medium;
                    case High;
                }
                PHP,
            'Item__I__' => <<<'PHP'
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

                #[Type]
                final class Item__I__
                {
                    /** @param list<string> $tags */
                    public function __construct(
                        #[Field(type: 'ID')] public string $id,
                        public string $name,
                        public int $rank,
                        public float $weight,
                        public bool $active,
                        public Level__I__ $level,
                        #[Field(of: 'string')] public array $tags,
                    ) {}
                }
                PHP,
            'CreateItem__I__' => <<<'PHP'
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;

                #[Input]
                final readonly class CreateItem__I__
                {
                    /** @param list<string> $tags */
                    public function __construct(
                        #[Field(rules: ['min:2', 'max:50'])] public string $name,
                        #[Field(rules: ['integer', 'min:0'])] public int $rank,
                        public Level__I__ $level,
                        #[Field(of: 'string')] public array $tags,
                    ) {}
                }
                PHP,
            'Item__I__Actions' => <<<'PHP'
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

                final class Item__I__Actions
                {
                    #[Query]
                    public function item__I__(string $id): Item__I__
                    {
                        return new Item__I__($id, 'Item __I__', __I__, __I__.5, true, Level__I__::Medium, ['alpha', 'beta']);
                    }

                    #[Mutation]
                    public function createItem__I__(CreateItem__I__ $input): Item__I__
                    {
                        return new Item__I__('created', $input->name, $input->rank, 0.5, false, $input->level, $input->tags);
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
            'Greeter' => <<<'PHP'
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

                final class Greeter
                {
                    #[Query]
                    public function greet(string $name): string
                    {
                        return "Hello, {$name}!";
                    }
                }
                PHP,
            'Genre' => <<<'PHP'
                enum Genre
                {
                    case Fiction;
                    case NonFiction;
                    case Poetry;
                    case Drama;
                }
                PHP,
            'Person' => <<<'PHP'
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

                #[Type]
                final class Person
                {
                    public function __construct(
                        public string $name,
                        public string $country,
                    ) {}
                }
                PHP,
            'Chapter' => <<<'PHP'
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

                #[Type]
                final class Chapter
                {
                    public function __construct(
                        public int $number,
                        public string $title,
                    ) {}
                }
                PHP,
            'Volume' => <<<'PHP'
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

                #[Type]
                final class Volume
                {
                    /**
                     * @param list<string> $tags
                     * @param list<Chapter> $chapters
                     */
                    public function __construct(
                        #[Field(type: 'ID')] public string $id,
                        public string $title,
                        public int $pages,
                        public float $ratio,
                        public bool $available,
                        public Genre $genre,
                        public Person $author,
                        #[Field(of: 'string')] public array $tags,
                        #[Field(of: Chapter::class)] public array $chapters,
                    ) {}
                }
                PHP,
            'NewPerson' => <<<'PHP'
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;

                #[Input]
                final readonly class NewPerson
                {
                    public function __construct(
                        #[Field(rules: ['min:2', 'max:60'])] public string $name,
                        #[Field(rules: ['size:2'])] public string $country,
                    ) {}
                }
                PHP,
            'NewVolume' => <<<'PHP'
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;

                #[Input]
                final readonly class NewVolume
                {
                    /** @param list<string> $tags */
                    public function __construct(
                        #[Field(rules: ['min:2', 'max:120'])] public string $title,
                        #[Field(rules: ['integer', 'min:1', 'max:5000'])] public int $pages,
                        public Genre $genre,
                        public NewPerson $author,
                        #[Field(of: 'string')] public array $tags,
                    ) {}
                }
                PHP,
            'Catalog' => <<<'PHP'
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

                final class Catalog
                {
                    /** @return list<Volume> */
                    #[Query(of: Volume::class)]
                    public function volumes(int $count): array
                    {
                        $volumes = [];

                        for ($n = 1; $n <= $count; $n++) {
                            $volumes[] = new Volume(
                                "v{$n}",
                                "Volume {$n}",
                                100 + $n,
                                $n / 4,
                                $n % 2 === 0,
                                Genre::cases()[$n % 4],
                                new Person('Author ' . ($n % 10), 'NL'),
                                ['acme', 't' . ($n % 5)],
                                [new Chapter(1, 'One'), new Chapter(2, 'Two'), new Chapter(3, 'Three')],
                            );
                        }

                        return $volumes;
                    }

                    /** @return list<string> */
                    #[Query(of: 'string')]
                    public function search(
                        #[Arg(rules: ['min:3', 'max:40'])] string $term,
                        #[Arg(rules: ['integer', 'between:1,50'])] int $limit,
                    ): array {
                        return array_map(static fn(int $n): string => "{$term} {$n}", range(1, $limit));
                    }

                    /** @return list<Genre> */
                    #[Query(of: Genre::class)]
                    public function genres(Genre $after, int $count): array
                    {
                        $cases = Genre::cases();
                        $offset = (int) array_search($after, $cases, true);
                        $genres = [];

                        for ($n = 1; $n <= $count; $n++) {
                            $genres[] = $cases[($offset + $n) % 4];
                        }

                        return $genres;
                    }

                    #[Mutation]
                    public function createVolume(NewVolume $input): Volume
                    {
                        return new Volume(
                            'created',
                            $input->title,
                            $input->pages,
                            $input->pages / 100,
                            true,
                            $input->genre,
                            new Person($input->author->name, $input->author->country),
                            $input->tags,
                            [],
                        );
                    }
                }
                PHP,
            'Account' => <<<'PHP'
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

                #[Type]
                final class Account
                {
                    public function __construct(
                        public int $number,
                        public string $holder,
                        #[Authorize('viewBalance')] public int $balance,
                    ) {}
                }
                PHP,
            'Accounts' => <<<'PHP'
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

                final class Accounts
                {
                    /** @return list<Account> */
                    #[Query(of: Account::class), Authorize]
                    public function accounts(int $count): array
                    {
                        $accounts = [];

                        for ($n = 1; $n <= $count; $n++) {
                            $accounts[] = new Account($n, "Holder {$n}", $n * 100);
                        }

                        return $accounts;
                    }
                }
                PHP,
            'Author' => <<<'PHP'
                use Illuminate\Database\Eloquent\Model;
                use Illuminate\Database\Eloquent\Relations\HasMany;
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\Relation;
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

                #[Type]
                class Author extends Model
                {
                    public $timestamps = false;

                    protected $table = 'bench_authors';

                    protected $guarded = [];

                    #[Field(type: 'ID')]
                    public int $id { get => $this->getKey(); }

                    public string $name { get => $this->getAttribute('name'); }

                    /** @return HasMany<Book, $this> */
                    #[Field(of: Book::class), Relation]
                    public function books(): HasMany
                    {
                        return $this->hasMany(Book::class)->orderBy('id');
                    }
                }
                PHP,
            'Book' => <<<'PHP'
                use Illuminate\Database\Eloquent\Model;
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

                #[Type]
                class Book extends Model
                {
                    public $timestamps = false;

                    protected $table = 'bench_books';

                    protected $guarded = [];

                    #[Field(type: 'ID')]
                    public int $id { get => $this->getKey(); }

                    public string $title { get => $this->getAttribute('title'); }

                    public int $pages { get => $this->getAttribute('pages'); }
                }
                PHP,
            'Library' => <<<'PHP'
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

                final class Library
                {
                    /** @return list<Author> */
                    #[Query(of: Author::class)]
                    public function authors(): array
                    {
                        return Author::query()->orderBy('id')->get()->all();
                    }
                }
                PHP,
        ];
    }
}
