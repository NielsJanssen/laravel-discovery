<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Inference;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorImpl;
use Illuminate\Support\Collection;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Paginated;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Pagination;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class NovelQuery
{
    #[Query]
    public function novel(): Novel
    {
        return new Novel('Dune', new Novel('Dune Messiah'));
    }

    #[Query]
    public function maybeNovel(): ?Novel
    {
        return null;
    }

    #[Query(nullable: true)]
    public function widenedNovel(): Novel
    {
        return new Novel('Children of Dune');
    }

    /** @return list<Novel> */
    #[Query(of: Novel::class)]
    public function novels(): array
    {
        return [new Novel('Dune'), new Novel('Dune Messiah')];
    }

    /** @return iterable<Novel> */
    #[Query(of: Novel::class, nullableItems: true)]
    public function sparseNovels(): iterable
    {
        yield new Novel('Dune');
        yield null;
    }

    /** @return Collection<int, Novel>|null */
    #[Query(of: Novel::class)]
    public function novelCollection(): ?Collection
    {
        return collect([new Novel('God Emperor of Dune')]);
    }

    /** @return LengthAwarePaginator<int, Novel>|null */
    #[Query(type: Novel::class)]
    #[Paginated]
    public function optionalNovelPage(Pagination $pagination): ?LengthAwarePaginator
    {
        return $this->novelPage($pagination);
    }

    /** @return LengthAwarePaginator<int, Novel> */
    #[Query(type: Novel::class, nullable: true)]
    #[Paginated]
    public function maybeNovelPage(Pagination $pagination): LengthAwarePaginator
    {
        return $this->novelPage($pagination);
    }

    /** @return LengthAwarePaginator<int, Novel> */
    #[Query(type: Novel::class)]
    #[Paginated]
    public function novelPage(Pagination $pagination): LengthAwarePaginator
    {
        return new LengthAwarePaginatorImpl(
            items: [new Novel('Heretics of Dune')],
            total: 1,
            perPage: $pagination->limit,
            currentPage: $pagination->page,
        );
    }

    #[Mutation]
    public function renameNovel(string $title): Novel
    {
        return new Novel($title);
    }
}
