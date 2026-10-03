<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Omitted;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Omitted;
use Workbench\App\Models\User;

#[Input]
final readonly class AssignBook
{
    public function __construct(
        #[Authorize('edit')]
        public User|Omitted $editor = Omitted::Value,
        #[Authorize('review')]
        public User|Omitted|null $reviewer = Omitted::Value,
        public User|Omitted $owner = Omitted::Value,
    ) {}
}
