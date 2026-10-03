<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Reference;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class BookQuery
{
    #[Query]
    public function book(): Book
    {
        return new Book(
            id: '1',
            title: 'The Left Hand of Darkness',
            subtitle: null,
            genre: Genre::Fiction,
            author: new AuthorSummary('Ursula K. Le Guin'),
            tags: ['classic', 'science fiction'],
            isbn: '9780441478125',
            internalNotes: 'never shown',
        );
    }
}
