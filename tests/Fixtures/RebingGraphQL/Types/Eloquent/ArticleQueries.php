<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Eloquent;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class ArticleQueries
{
    #[Query]
    public function article(#[Arg('id')] Article $article): Article
    {
        return $article;
    }

    #[Mutation]
    public function retitleArticle(#[Arg('id')] Article $article, string $title): Article
    {
        $article->title = $title;
        $article->save();

        return $article;
    }
}
