<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Container\Attributes\CurrentUser;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Context;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Root;
use Workbench\App\Models\User;

class ContainerInjectionQuery
{
    #[Query(name: 'containerInjected')]
    public function resolve(
        string $name,
        #[Root]
        mixed $root,
        #[Context]
        mixed $context,
        ?ResolveInfo $info,
        ContainerService $service,
    ): string {
        return $name . $service->suffix();
    }

    #[Query]
    public function mapped(string $name, #[Root] mixed $root, ?ResolveInfo $info): string
    {
        return $name . ($root === null ? '' : ":$root");
    }

    #[Query]
    public function signedIn(string $greeting, #[CurrentUser] ?User $user): string
    {
        return $greeting . ', ' . ($user->email ?? 'guest');
    }

    #[Query]
    public function joined(string ...$parts): string
    {
        return implode('+', array_map(static fn(int|string $key, string $part): string => "$key=$part", array_keys($parts), $parts));
    }
}
