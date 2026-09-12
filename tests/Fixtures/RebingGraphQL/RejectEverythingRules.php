<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\ArgumentRules;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\RuleProvider;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredAction;

/** A third-party style rules provider: no knowledge of our validation package at all. */
final class RejectEverythingRules implements RuleProvider
{
    public function rulesFor(DiscoveredAction $action, array $args): ArgumentRules
    {
        return new ArgumentRules(
            rules: ['name' => ['in:impossible']],
            messages: ['name.in' => 'Nothing gets past me.'],
        );
    }
}
