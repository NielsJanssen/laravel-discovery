<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Loaders;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class LoaderQueries
{
    #[Query(of: Writer::class)]
    public function writers(): array
    {
        return Writer::query()->orderBy('id')->get()->all();
    }

    #[Query(of: Writer::class)]
    public function writersWithNovels(): array
    {
        return Writer::query()->with('novels')->orderBy('id')->get()->all();
    }

    #[Query(of: Novel::class)]
    public function novels(): array
    {
        return Novel::query()->orderBy('id')->get()->all();
    }

    #[Query(of: Review::class)]
    public function reviews(): array
    {
        return [new Review(1, 1), new Review(2, 2), new Review(3, 1), new Review(4, 999)];
    }
}
