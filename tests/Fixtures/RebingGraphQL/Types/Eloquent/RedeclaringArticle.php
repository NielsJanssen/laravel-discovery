<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Eloquent;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type(name: 'Article', description: 'An article stored in the database')]
class RedeclaringArticle extends Article
{
    public $timestamps = false;

    public $incrementing = false;
}
