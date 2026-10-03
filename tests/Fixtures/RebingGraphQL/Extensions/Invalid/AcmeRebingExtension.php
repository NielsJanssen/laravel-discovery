<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Extensions\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeExtension;
use Rebing\GraphQL\Support\Type as RebingType;

#[TypeExtension('AcmeUser')]
final class AcmeRebingExtension extends RebingType
{
    protected $attributes = ['name' => 'AcmeRebingExtension'];
}
