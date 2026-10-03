<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;
use Tests\Fixtures\RebingGraphQL\Inputs\Chapter;

final class ArgTypeOnInputQuery
{
    #[Mutation]
    public function addChapter(#[Arg(type: 'ChapterInput')] Chapter $chapter): string
    {
        return $chapter->title;
    }
}
