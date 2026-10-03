<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Mappers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use Tests\Fixtures\RebingGraphQL\Enums\Mood;

final class TierQuery
{
    #[Query]
    public function customer(): Customer
    {
        return new Customer('Cheerful');
    }

    #[Query]
    public function tierOf(Mood $tier): string
    {
        return $tier::class . '::' . $tier->name;
    }
}
