<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use Workbench\App\Models\User;

#[Input]
final class ModelListProperty
{
    /** @var list<User> */
    #[Field(of: User::class)]
    public array $users = [];
}
