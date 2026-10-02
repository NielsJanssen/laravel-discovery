<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Reference;

use Illuminate\Support\Str;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Ignore;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type(description: 'A published book')]
final class Book
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        #[Field(type: 'ID')]
        public string $id,
        public string $title,
        public ?string $subtitle,
        public Genre $genre,
        public AuthorSummary $author,
        #[Field(of: 'string')]
        public array $tags = [],
        #[Field(description: 'ISBN-13', deprecationReason: 'Use identifiers')]
        public ?string $isbn = null,
        #[Ignore]
        public string $internalNotes = '',
    ) {}

    public string $slug { get => Str::slug($this->title); }

    #[Field(description: 'The title, shortened')]
    public function excerpt(int $length = 80): string
    {
        return Str::limit($this->title, $length);
    }

    /**
     * @return list<Book>
     */
    #[Field(of: Book::class)]
    public function related(Recommender $recommender, int $limit = 5): array
    {
        return $recommender->for($this, $limit);
    }
}
