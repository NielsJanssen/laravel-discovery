<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation\Database;

use NielsJanssen\Laravel\Validation\Rule\Unique;
use NielsJanssen\Laravel\Validation\ValidationContext;
use Workbench\App\Models\User;

/** The key comes off the object under validation, so the closure reads it per call. */
final class IgnoreOwnId
{
    public ?int $id = null;

    #[Unique(User::class, 'email', ignore: static function (ValidationContext $context): ?int {
        return $context->root->id;
    })]
    public string $email = 'fresh@example.com';
}
