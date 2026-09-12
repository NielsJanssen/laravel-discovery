<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use NielsJanssen\Laravel\Validation\Rule\Can;
use Workbench\App\Models\User;

class CanOnBoundModelQuery
{
    #[Query]
    public function canOnBoundModel(#[Arg('id')] #[Can('view')] User $user): string
    {
        return $user->name;
    }
}
