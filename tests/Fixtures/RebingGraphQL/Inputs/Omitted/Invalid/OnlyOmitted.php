<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Omitted\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Omitted;

#[Input]
final readonly class OnlyOmitted
{
    public function __construct(
        public ?Omitted $value = Omitted::Value,
    ) {}
}
