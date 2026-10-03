<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\AsArgs;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Validation\Rule\Max;

#[Input]
final readonly class Parcel
{
    public function __construct(
        #[Field(name: 'weightKg'), Max(30, message: 'A parcel weighs 30 kg at most.')]
        public int $weight,
    ) {}
}
