<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Schedule;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
final readonly class Scheduled
{
    /**
     * @param string|Frequency|\Closure(\Illuminate\Console\Scheduling\Event): void $schedule
     */
    public function __construct(
        public string|Frequency|\Closure $schedule,
        public ?string                   $name = null,
    ) {}
}
