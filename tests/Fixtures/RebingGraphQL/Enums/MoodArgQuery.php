<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Enums;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class MoodArgQuery
{
    #[Query]
    public function mood(Mood $mood, ?Mood $fallback = null, Mood $preset = Mood::Calm): string
    {
        return implode(',', [$mood::class . '::' . $mood->name, $fallback->name ?? 'none', $preset->name]);
    }
}
