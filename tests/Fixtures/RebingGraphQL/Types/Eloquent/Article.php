<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Eloquent;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Ignore;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type(description: 'An article stored in the database')]
class Article extends Model
{
    public static int $constructed = 0;

    protected $table = 'hooked_articles';

    protected $guarded = [];

    protected $attributes = ['status' => 'draft', 'word_count' => 0, 'copies_sold' => 0];

    #[Ignore]
    public bool $previewing = false;

    public function __construct(array $attributes = [])
    {
        static::$constructed++;

        parent::__construct($attributes);
    }

    #[Field(type: 'ID')]
    public int $id { get => $this->getKey(); }

    public string $title {
        get => $this->getAttribute('title');
        set(string $value) {
            $this->setAttribute('title', $value);
        }
    }

    #[Field(type: 'String')]
    public ?CarbonImmutable $publishedAt { get => $this->getAttribute('published_at'); }

    public int $wordCount { get => $this->getAttribute('word_count'); }

    public ArticleStatus $status { get => $this->getAttribute('status'); }

    #[Authorize('viewSales')]
    public int $copiesSold { get => $this->getAttribute('copies_sold'); }

    #[Field(description: 'The title in capitals')]
    public function headline(): string
    {
        return Str::upper($this->title);
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'immutable_datetime',
            'word_count' => 'integer',
            'status' => ArticleStatus::class,
            'copies_sold' => 'integer',
        ];
    }
}
