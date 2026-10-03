<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Replacement;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type(description: 'A user with a tier', replace: true)]
class AcmeUserWithTier extends AcmeUserWithBilling
{
    public ?string $tier = 'gold';
}
