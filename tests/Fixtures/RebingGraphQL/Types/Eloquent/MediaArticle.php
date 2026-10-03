<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Eloquent;

use Illuminate\Database\Eloquent\Model;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use Tests\Fixtures\RebingGraphQL\Types\Eloquent\Concerns\HasSlug;
use Tests\Fixtures\RebingGraphQL\Types\Eloquent\Concerns\InteractsWithMedia;

#[Type]
class MediaArticle extends Model
{
    use HasSlug;
    use InteractsWithMedia;

    public string $title { get => $this->getAttribute('title'); }
}
