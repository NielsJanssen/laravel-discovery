<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class ChapterArgQuery
{
    /**
     * @param  array<string, mixed>  $chapter
     */
    #[Query]
    public function rawChapter(#[Arg(type: 'ChapterInput')] array $chapter): string
    {
        return (string) $chapter['title'];
    }

    #[Query]
    public function pairedChapter(#[Arg(rules: ['array', 'size:2'])] Chapter $chapter): string
    {
        return $chapter->title;
    }
}
