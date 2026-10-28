<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;

final class ListArgMutations
{
    /** @var array<array-key, mixed>|null */
    public static ?array $received = null;

    #[Mutation]
    public function addChapters(#[Arg(of: Chapter::class)] array $chapters): string
    {
        self::$received = $chapters;

        return implode(', ', array_map(static fn(Chapter $chapter): string => $chapter->title, $chapters));
    }

    #[Mutation]
    public function addBooks(#[Arg(of: CreateBook::class)] array $books): string
    {
        self::$received = $books;

        return implode(', ', array_map(static fn(CreateBook $book): string => $book->title, $books));
    }

    #[Mutation]
    public function saveProfiles(#[Arg('people', of: Profile::class, nullableItems: true)] ?array $profiles = null): int
    {
        self::$received = $profiles;

        return count($profiles ?? []);
    }

    #[Mutation]
    public function tagChapters(
        #[Arg(of: 'String')]
        array $tags = [],
        #[Arg(of: Genre::class)]
        array $genres = [],
    ): string {
        self::$received = ['tags' => $tags, 'genres' => $genres];

        return implode(',', [...$tags, ...array_map(static fn(Genre $genre): string => $genre->name, $genres)]);
    }
}
