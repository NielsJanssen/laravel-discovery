<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\AsArgs;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\ArgumentRules;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\InputRuleProvider;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\RuleProvider;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredAction;

final class SearchTermRules implements InputRuleProvider, RuleProvider
{
    public function rulesFor(DiscoveredAction $action, array $args): ArgumentRules
    {
        return new ArgumentRules([]);
    }

    public function rulesForInput(string $class, array $values): ArgumentRules
    {
        if ($class !== BookSearch::class) {
            return new ArgumentRules([]);
        }

        return new ArgumentRules(['term' => ['min:3']], ['term.min' => 'Search for three letters or more.']);
    }
}
