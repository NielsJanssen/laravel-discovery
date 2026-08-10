<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\ArgumentRuleSet;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\ArgumentRules;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredAction;

/** A second provider, to prove several coexist rather than one winning. */
final class RequireLongNameRules implements ArgumentRules
{
    public function rulesFor(DiscoveredAction $action, array $args): ArgumentRuleSet
    {
        return new ArgumentRuleSet(rules: ['name' => ['min:50']]);
    }
}
