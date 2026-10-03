<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Validation\Rule\Min;

#[Input]
final readonly class Chapter
{
    public function __construct(
        #[Min(2, message: 'A chapter title needs two letters.')]
        public string $title,
    ) {}
}
