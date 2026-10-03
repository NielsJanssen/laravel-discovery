<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Extensions\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeExtension;
use Tests\Fixtures\RebingGraphQL\Replacement\AcmeUser;

#[TypeExtension(AcmeUser::class)]
final class AcmeRivalExtras
{
    #[Field]
    public function invoices(): int
    {
        return 0;
    }
}
