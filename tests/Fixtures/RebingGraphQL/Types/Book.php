<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types;

final class Book
{
    public function __construct(
        public int $id = 1,
        public string $title = 'The Great Gatsby',
    ) {}
}
