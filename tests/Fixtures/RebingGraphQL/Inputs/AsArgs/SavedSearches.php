<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\AsArgs;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;

final class SavedSearches
{
    #[Mutation]
    public function saveSearch(BookSearch $search): string
    {
        return $search->term ?? 'all';
    }
}
