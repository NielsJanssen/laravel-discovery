<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Authorization;

use GraphQL\Type\Definition\ResolveInfo;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\AuthorizationGate;
use Workbench\App\Models\User;

final class StaffOnlyGate implements AuthorizationGate
{
    /** @var list<array{0: mixed, 1: ?string}> */
    public static array $calls = [];

    public function check(mixed $root, array $args, mixed $context, ?ResolveInfo $info): bool
    {
        self::$calls[] = [$root, $info?->fieldName];

        $user = auth()->user();

        return $user instanceof User && $user->name === 'staff';
    }
}
