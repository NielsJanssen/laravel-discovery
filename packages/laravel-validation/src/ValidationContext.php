<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation;

final readonly class ValidationContext
{
    public function __construct(
        public mixed $root,
        public string $path,
        public mixed $value,
    ) {}
}
