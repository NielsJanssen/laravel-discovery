<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Omitted;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Omitted;
use NielsJanssen\Laravel\Validation\Rule\Min;

#[Input]
final readonly class UpdateBook
{
    public function __construct(
        #[Min(2, message: 'A title needs two letters.')]
        public string|Omitted $title = Omitted::Value,
        public string|Omitted|null $subtitle = Omitted::Value,
    ) {}
}
