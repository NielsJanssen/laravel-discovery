<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Schemas;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use Tests\Fixtures\RebingGraphQL\Enums\Mood;

#[Input]
final readonly class AcmeReportFilter
{
    public function __construct(public Mood $severity) {}
}
