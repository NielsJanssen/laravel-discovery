<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Traits;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class TraitAuthorizedQuery
{
    use RequiresLogin;

    #[Query]
    public function traitLoggedIn(): string
    {
        return 'ok';
    }
}
