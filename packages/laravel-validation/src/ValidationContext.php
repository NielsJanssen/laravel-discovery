<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation;

class ValidationContext
{
    public function __construct(
        public readonly mixed $root,
        public readonly string $path,
        public readonly mixed $value,
    ) {}
}
