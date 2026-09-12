<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation\Database;

use NielsJanssen\Laravel\Validation\Rule\Unique;
use Workbench\App\Models\User;

/** A soft-deleted row does not hold its email hostage. */
final class UniqueWithoutTrashed
{
    #[Unique(User::class, 'email', withoutTrashed: true)]
    public string $email = 'fresh@example.com';
}
