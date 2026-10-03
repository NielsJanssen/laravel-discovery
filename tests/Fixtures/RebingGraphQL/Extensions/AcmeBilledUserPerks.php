<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Extensions;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Root;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeExtension;
use Tests\Fixtures\RebingGraphQL\Replacement\AcmeUser;
use Tests\Fixtures\RebingGraphQL\Replacement\AcmeUserWithBilling;

#[TypeExtension(AcmeUserWithBilling::class)]
final class AcmeBilledUserPerks
{
    #[Field]
    public function perks(#[Root] AcmeUser $user): string
    {
        return $user instanceof AcmeUserWithBilling ? "perks for {$user->billingReference}" : 'no perks';
    }
}
