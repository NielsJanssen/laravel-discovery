<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Mappers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class TicketQuery
{
    #[Query]
    public function ticket(string $notes = ''): Ticket
    {
        return new Ticket(null, $notes, new Money(500, 'EUR'));
    }
}
