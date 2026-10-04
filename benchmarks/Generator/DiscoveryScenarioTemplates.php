<?php

declare(strict_types=1);

namespace Benchmarks\Generator;

final class DiscoveryScenarioTemplates
{
    /**
     * The scenario classes present once at every size, keyed by file name.
     *
     * @return array<string, string>
     */
    public static function features(): array
    {
        return [
            'AuthorLookup' => <<<'PHP'
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

                final class AuthorLookup
                {
                    #[Query]
                    public function author(#[Arg('id')] Author $author): Author
                    {
                        return $author;
                    }
                }
                PHP,
            'Renamer' => <<<'PHP'
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;

                final class Renamer
                {
                    #[Mutation]
                    public function renameVolume(
                        #[Arg(rules: ['min:3', 'max:20'])] string $title,
                        #[Arg(rules: ['integer', 'between:1,100'])] int $edition,
                    ): string {
                        return "{$title} #{$edition}";
                    }
                }
                PHP,
            'BookPages' => <<<'PHP'
                use Illuminate\Contracts\Pagination\LengthAwarePaginator;
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Paginated;
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Pagination;
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

                final class BookPages
                {
                    /** @return LengthAwarePaginator<int, Book> */
                    #[Query(type: 'Book'), Paginated]
                    public function bookPage(Pagination $pagination): LengthAwarePaginator
                    {
                        return $pagination(Book::query()->orderBy('id'));
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
            'Shouting' => <<<'PHP'
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Middleware;
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

                final class Shouting
                {
                    #[Query, Middleware(ShoutMiddleware::class)]
                    public function shout(string $text): string
                    {
                        return "{$text}!";
                    }
                }
                PHP,
            'Vault' => <<<'PHP'
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorization;
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

                final class Vault
                {
                    #[Query]
                    public function vault(int $number, Authorization $authorization): string
                    {
                        $authorization->authorize('openVault', $number);

                        return "Vault {$number} is open";
                    }
                }
                PHP,
            'Gadget' => <<<'PHP'
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Ignore;
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

                #[Type(factory: GadgetFields::class)]
                final class Gadget
                {
                    /** @param array<string, int> $specs */
                    public function __construct(
                        public string $name,
                        #[Ignore] public array $specs = [],
                    ) {}
                }
                PHP,
            'GadgetFields' => <<<'PHP'
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeContext;
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeFactory;

                final readonly class GadgetFields implements TypeFactory
                {
                    public function fields(TypeContext $context): iterable
                    {
                        foreach (['width', 'height', 'depth', 'weight'] as $key) {
                            yield new Field(name: $key, type: 'int', nullable: true, resolve: static fn(Gadget $root): ?int => $root->specs[$key] ?? null);
                        }
                    }
                }
                PHP,
            'Gadgets' => <<<'PHP'
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

                final class Gadgets
                {
                    /** @return list<Gadget> */
                    #[Query(of: Gadget::class)]
                    public function gadgets(int $count): array
                    {
                        $gadgets = [];

                        for ($n = 1; $n <= $count; $n++) {
                            $specs = ['width' => $n, 'height' => $n * 2, 'weight' => $n * 10];

                            if ($n % 3 !== 0) {
                                $specs['depth'] = $n + 1;
                            }

                            $gadgets[] = new Gadget("Gadget {$n}", $specs);
                        }

                        return $gadgets;
                    }
                }
                PHP,
            'ReadingProvider' => <<<'PHP'
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Position;
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeDefinition;
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeProvider;

                final class ReadingProvider implements TypeProvider
                {
                    public function types(): iterable
                    {
                        yield new TypeDefinition(
                            name: 'Reading',
                            kind: Position::Output,
                            fields: static fn(): array => [
                                new Field(name: 'sensor', type: 'string'),
                                new Field(name: 'value', type: 'float'),
                                new Field(name: 'unit', type: 'string', nullable: true),
                            ],
                        );
                    }
                }
                PHP,
            'Readings' => <<<'PHP'
                use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

                final class Readings
                {
                    /** @return list<array{sensor: string, value: float, unit: ?string}> */
                    #[Query(of: 'Reading')]
                    public function readings(int $count): array
                    {
                        $readings = [];

                        for ($n = 1; $n <= $count; $n++) {
                            $readings[] = ['sensor' => "S{$n}", 'value' => $n / 8, 'unit' => $n % 2 === 0 ? 'kPa' : null];
                        }

                        return $readings;
                    }
                }
                PHP,
        ];
    }
}
