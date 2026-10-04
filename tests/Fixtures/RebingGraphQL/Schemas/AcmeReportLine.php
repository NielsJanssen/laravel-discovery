<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Schemas;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final readonly class AcmeReportLine
{
    public function __construct(public string $text) {}
}
