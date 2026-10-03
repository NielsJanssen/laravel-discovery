<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Enums;

use Illuminate\Validation\Rule;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class MoodRuleQuery
{
    #[Query]
    public function calmOnly(
        #[Arg(rules: static function (): array {
            return [Rule::enum(Mood::class)->only([Mood::Calm, Mood::Cheerful])];
        })]
        Mood $mood,
    ): string {
        return $mood->name;
    }
}
