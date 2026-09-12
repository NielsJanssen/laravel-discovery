<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation\Database;

use Illuminate\Contracts\Database\Query\Builder;
use NielsJanssen\Laravel\Validation\Rule\Exists;
use NielsJanssen\Laravel\Validation\ValidationContext;
use Workbench\App\Models\User;

final class ExistsWhereClosure
{
    public string $name = 'Ada';

    #[Exists(User::class, 'email', where: static function (Builder $query, ValidationContext $context): void {
        $query->where('name', $context->root->name);
    })]
    public string $email = 'ada@example.com';
}
