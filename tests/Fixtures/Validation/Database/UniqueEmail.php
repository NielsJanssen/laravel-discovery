<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation\Database;

use NielsJanssen\Laravel\Validation\Rule\Unique;
use Workbench\App\Models\User;

final class UniqueEmail
{
    #[Unique(User::class, 'email', message: 'That email is taken.')]
    public string $email = 'fresh@example.com';
}
