<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Replacement;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class AcmeUserQuery
{
    #[Query]
    public function user(): AcmeUser
    {
        return new AcmeUser();
    }

    #[Query]
    public function billedUser(): AcmeUserWithBilling
    {
        return new AcmeUserWithBilling();
    }
}
