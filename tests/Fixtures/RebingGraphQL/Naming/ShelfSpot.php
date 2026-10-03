<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Naming;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Validation\Rule\Max;

#[Input]
final readonly class ShelfSpot
{
    public function __construct(
        #[Max(9, message: 'A shelf has nine slots.')]
        public int $slotIndex,
    ) {}
}
