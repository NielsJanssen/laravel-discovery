<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\AsArgs\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\AsArgs;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use Tests\Fixtures\RebingGraphQL\Inputs\AsArgs\BookSearch;
use Workbench\App\Models\User;

final class CollidesWithModelBinding
{
    #[Query]
    public function search(#[Arg('year')] User $owner, #[AsArgs] BookSearch $search): string
    {
        return 'never';
    }
}
