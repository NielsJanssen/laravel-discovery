<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Extensions\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeExtension;
use Tests\Fixtures\RebingGraphQL\ContainerService;
use Tests\Fixtures\RebingGraphQL\Replacement\AcmeUser;

#[TypeExtension(AcmeUser::class)]
final class AcmeUnregisteredReturn
{
    #[Field]
    public function service(): ContainerService
    {
        return new ContainerService();
    }
}
