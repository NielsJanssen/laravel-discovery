<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation\Database;

use NielsJanssen\Laravel\Validation\Rule\Unique;
use NielsJanssen\Laravel\Validation\ValidationContext;
use Workbench\App\Models\User;

/** A returned model is handed to Laravel's ignoreModel(), key column and all. */
final class IgnoreOwnModel
{
    public ?User $user = null;

    #[Unique(User::class, 'email', ignore: static function (ValidationContext $context): ?User {
        return $context->root->user;
    })]
    public string $email = 'fresh@example.com';
}
