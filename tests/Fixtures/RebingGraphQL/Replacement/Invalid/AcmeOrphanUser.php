<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Replacement\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use Tests\Fixtures\RebingGraphQL\ContainerService;

#[Type(replace: true)]
class AcmeOrphanUser extends ContainerService {}
