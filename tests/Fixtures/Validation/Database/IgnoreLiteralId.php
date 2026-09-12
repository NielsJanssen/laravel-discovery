<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation\Database;

use NielsJanssen\Laravel\Validation\Rule\Unique;
use Workbench\App\Models\User;

/** The row being edited is known up front, so the key can be written out. */
final class IgnoreLiteralId
{
    #[Unique(User::class, 'email', ignore: 5)]
    public string $email = 'fresh@example.com';
}
