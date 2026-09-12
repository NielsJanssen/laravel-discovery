<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use Workbench\App\Models\User;

class ExplicitTypeReturnQuery
{
    #[Query(type: 'User')]
    public function nullableReturn(): ?User
    {
        return null;
    }

    #[Query(type: 'User')]
    public function nonNullableReturn(): User
    {
        return new User();
    }

    #[Query(type: 'User', nullable: true)]
    public function explicitlyNullable(): User
    {
        return new User();
    }

    #[Query(type: 'User', list: true)]
    public function nullableList(): ?array
    {
        return null;
    }

    #[Query(type: 'User')]
    public function undeclaredReturn()
    {
        return null;
    }
}
