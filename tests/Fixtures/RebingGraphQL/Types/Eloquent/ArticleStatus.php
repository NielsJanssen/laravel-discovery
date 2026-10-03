<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Eloquent;

enum ArticleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
