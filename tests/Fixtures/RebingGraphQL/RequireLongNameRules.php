<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\RuleSet;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\RuleProvider;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredAction;

/** A second provider, to prove several coexist rather than one winning. */
final class RequireLongNameRules implements RuleProvider
{
    public function rulesFor(DiscoveredAction $action, array $args): RuleSet
    {
        return new RuleSet(rules: ['name' => ['min:50']]);
    }
}
