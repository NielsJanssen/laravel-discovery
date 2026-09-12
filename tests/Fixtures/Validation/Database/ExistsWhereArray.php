<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation\Database;

use NielsJanssen\Laravel\Validation\Rule\Exists;
use Workbench\App\Models\User;

/** A list becomes whereIn and a null becomes whereNull. */
final class ExistsWhereArray
{
    #[Exists(User::class, 'email', where: ['name' => ['Ada', 'Bob'], 'remember_token' => null])]
    public string $email = 'ada@example.com';
}
