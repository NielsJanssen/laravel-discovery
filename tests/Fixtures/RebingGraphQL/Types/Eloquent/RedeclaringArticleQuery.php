<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Eloquent;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class RedeclaringArticleQuery
{
    #[Query]
    public function article(#[Arg('id')] RedeclaringArticle $article): RedeclaringArticle
    {
        return $article;
    }
}
