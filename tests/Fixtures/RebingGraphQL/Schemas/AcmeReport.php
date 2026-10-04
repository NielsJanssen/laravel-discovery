<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Schemas;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use Tests\Fixtures\RebingGraphQL\Enums\Mood;

#[Type]
final class AcmeReport
{
    /**
     * @param  list<AcmeReportLine>  $lines
     */
    public function __construct(
        public string $title,
        public Mood $severity,
        #[Field(of: AcmeReportLine::class)]
        public array $lines,
    ) {}
}
