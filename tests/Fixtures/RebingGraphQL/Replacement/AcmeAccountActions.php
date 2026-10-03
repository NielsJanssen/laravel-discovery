<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Replacement;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class AcmeAccountActions
{
    #[Query]
    public function account(): AcmeAccount
    {
        return new AcmeAccount();
    }

    #[Mutation]
    public function saveAccount(AcmeAccount $account): string
    {
        return $account->label;
    }
}
