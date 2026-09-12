<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation\Database;

use NielsJanssen\Laravel\Validation\Rule\Exists;
use Workbench\App\Models\User;

final class ExistsEmail
{
    #[Exists(User::class, 'email', message: 'No such account.')]
    public string $email = 'ada@example.com';
}
