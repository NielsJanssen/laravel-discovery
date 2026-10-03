<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;

#[Input(description: 'Nothing takes this')]
final readonly class Unused
{
    public function __construct(public Shade $shade, public Chapter $chapter) {}
}
