<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\AsArgs;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Validation\Rule\Min;
use NielsJanssen\Laravel\Validation\Rule\Valid;

#[Input]
final readonly class Shipment
{
    public function __construct(
        #[Min(2, message: 'A label needs two letters.')]
        public string $label,
        #[Valid]
        public Parcel $parcel,
    ) {}
}
