<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Enums;

use Rebing\GraphQL\Support\EnumType;

final class HandWrittenMoodType extends EnumType
{
    protected $attributes = [
        'name' => 'Mood',
        'description' => 'Written by hand',
        'values' => [
            'Calm' => ['value' => Mood::Calm],
            'Cheerful' => ['value' => Mood::Cheerful],
            'Gloomy' => ['value' => Mood::Gloomy],
        ],
    ];
}
