<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;

#[Input]
final readonly class OptionalRequiredParam
{
    public function __construct(
        #[Field(nullable: true)]
        public string $title,
    ) {}
}
