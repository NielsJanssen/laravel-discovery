<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Schedule;

class Cron
{
    public function __construct(
        public string $expression,
    ) {}
}
