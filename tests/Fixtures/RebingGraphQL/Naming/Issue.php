<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Naming;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class Issue
{
    public int $issue_number = 7;

    #[Field]
    public function page_range(int $first_page = 1): string
    {
        return "$first_page-99";
    }
}
