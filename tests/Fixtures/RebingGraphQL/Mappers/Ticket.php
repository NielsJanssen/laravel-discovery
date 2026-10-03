<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Mappers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class Ticket
{
    public function __construct(
        public ?int $id,
        public string $notes,
        #[Field(nullable: true)]
        public Money $deposit,
    ) {}
}
