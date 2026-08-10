<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\ArgumentRuleSet;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\ArgumentRules;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredAction;

/** A third-party style rules provider: no knowledge of our validation package at all. */
final class RejectEverythingRules implements ArgumentRules
{
    public function rulesFor(DiscoveredAction $action, array $args): ArgumentRuleSet
    {
        return new ArgumentRuleSet(
            rules: ['name' => ['in:impossible']],
            messages: ['name.in' => 'Nothing gets past me.'],
        );
    }
}
