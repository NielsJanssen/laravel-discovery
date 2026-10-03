<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Eloquent;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class MediaArticleQuery
{
    #[Query]
    public function mediaArticle(#[Arg('id')] MediaArticle $article): MediaArticle
    {
        return $article;
    }
}
