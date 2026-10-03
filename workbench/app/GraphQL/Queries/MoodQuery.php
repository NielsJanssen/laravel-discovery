<?php

declare(strict_types=1);

namespace Workbench\App\GraphQL\Queries;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use Workbench\App\GraphQL\Types\Mood;

class MoodQuery
{
    #[Query(name: 'mood')]
    public function resolve(Mood $mood = Mood::Calm): Mood
    {
        return $mood;
    }
}
