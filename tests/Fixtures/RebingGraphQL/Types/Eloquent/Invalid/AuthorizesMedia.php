<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Eloquent\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;

trait AuthorizesMedia
{
    #[Authorize]
    public array $mediaConversions = [];
}
