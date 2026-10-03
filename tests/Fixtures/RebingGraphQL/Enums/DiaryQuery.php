<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Enums;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class DiaryQuery
{
    #[Query]
    public function diary(): Diary
    {
        return new Diary(Mood::Cheerful, Genre::Fiction);
    }
}
