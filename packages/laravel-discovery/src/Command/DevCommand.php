<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Command;

use Attribute;
use Illuminate\Foundation\DevCommandColor;

/** Runs the discovered command as a process of `php artisan dev`. */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class DevCommand
{
    public function __construct(
        public ?string $name = null,
        public string $arguments = '',
        public ?DevCommandColor $color = null,
    ) {}
}
