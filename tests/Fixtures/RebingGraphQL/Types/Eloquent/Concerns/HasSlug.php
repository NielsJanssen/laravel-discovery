<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Eloquent\Concerns;

use Illuminate\Support\Str;

trait HasSlug
{
    public string $slug { get => Str::slug($this->getAttribute('title')); }
}
