<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation;

class DiscoveredRules
{
    public function __construct(
        public readonly RuleSet $rules,
    ) {}
}
