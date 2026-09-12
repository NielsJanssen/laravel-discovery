<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use NielsJanssen\Laravel\Validation\Rule\Numeric;
use Workbench\App\Models\User;

class ModelBindingQuery
{
    #[Query]
    public function requiredById(#[Arg('id')] User $user): string
    {
        return $user->name;
    }

    #[Query]
    public function optionalUser(?User $user = null): string
    {
        return $user?->name ?? 'none';
    }

    #[Query]
    public function typedId(#[Arg('id', type: 'String')] User $user): string
    {
        return $user->name;
    }

    #[Query]
    public function bareUser(User $user): string
    {
        return $user->name;
    }

    #[Query]
    public function validatedBinding(#[Arg('id')] #[Numeric] User $user): string
    {
        return $user->name;
    }

    #[Query]
    public function extraRules(#[Arg('id', rules: ['integer'])] User $user): string
    {
        return $user->name;
    }
}
