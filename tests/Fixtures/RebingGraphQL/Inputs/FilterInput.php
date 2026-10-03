<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;

#[Input]
final readonly class FilterInput
{
    public function __construct(public ?string $term = null) {}
}
