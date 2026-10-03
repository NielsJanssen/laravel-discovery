<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;

#[Input(name: 'ChapterInput')]
final class DuplicateInputName
{
    public string $title = '';
}
