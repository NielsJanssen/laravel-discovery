<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\AsArgs;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\AsArgs;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class SearchQueries
{
    public static ?BookSearch $search = null;

    public static ?Sorting $sorting = null;

    #[Query]
    public function findBooks(#[AsArgs] BookSearch $search): string
    {
        self::$search = $search;

        return $search->term ?? 'all';
    }

    #[Query]
    public function sortedBooks(#[AsArgs] BookSearch $search, #[AsArgs] Sorting $sorting): string
    {
        self::$search = $search;
        self::$sorting = $sorting;

        return $sorting->sortBy . ($sorting->descending ? ' desc' : ' asc');
    }
}
