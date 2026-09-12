<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation;

final readonly class DiscoveredRules
{
    public function __construct(
        public RuleSet $rules,
    ) {}
}
