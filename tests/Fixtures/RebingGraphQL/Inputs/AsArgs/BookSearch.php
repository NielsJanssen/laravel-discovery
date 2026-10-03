<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\AsArgs;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Validation\Rule\Between;

#[Input]
final readonly class BookSearch
{
    public function __construct(
        public ?string $term = null,
        public ?Shelf $shelf = null,
        #[Between(1450, 2100)]
        public ?int $year = null,
    ) {}
}
