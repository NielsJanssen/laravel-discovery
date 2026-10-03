<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Replacement\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type(replace: true)]
class AcmeOrphanUser extends AcmePlainUser {}
