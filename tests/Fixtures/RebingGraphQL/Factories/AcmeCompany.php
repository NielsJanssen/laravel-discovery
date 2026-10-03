<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Factories;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Ignore;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type(factory: AcmeCompanyFields::class)]
final class AcmeCompany
{
    public string $name = 'Acme';

    #[Ignore]
    public string $region = 'EU';

    /** @var list<string> */
    #[Ignore]
    public array $tags = ['anvils'];
}
