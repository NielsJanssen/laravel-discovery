<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use Illuminate\Validation\Rules\In;
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

    #[Query]
    public function closureRules(#[Arg('id', rules: static function (): array {
        return ['integer'];
    })] User $user): string
    {
        return $user->name;
    }

    #[Query]
    public function closurePipeRules(#[Arg('id', rules: static function (): string {
        return 'integer|min:1';
    })] User $user): string
    {
        return $user->name;
    }

    #[Query]
    public function closureRuleObject(#[Arg('id', rules: static function (): object {
        return new In([1]);
    })] User $user): string
    {
        return $user->name;
    }

    #[Query]
    public function nullableClosureRules(#[Arg('id', rules: static function (): string {
        return 'integer';
    })] ?User $user = null): string
    {
        return $user?->name ?? 'none';
    }
}
