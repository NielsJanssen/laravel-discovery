<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class UntypedProperty
{
    /** @var string */
    public $value = 'untyped';
}
