<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Naming;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\FieldCase;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type(naming: TrailingUnderscore::class), Input(naming: FieldCase::Snake)]
final class SnakeNote
{
    public function __construct(
        public string $noteText = '',
    ) {}
}
