<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Authorization;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class MemberQuery
{
    #[Query]
    public function member(string $name = 'Ada'): Member
    {
        return new Member($name);
    }
}
