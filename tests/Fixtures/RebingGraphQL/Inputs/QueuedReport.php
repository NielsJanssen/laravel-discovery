<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs;

use Illuminate\Bus\Queueable;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;

#[Input]
final class QueuedReport
{
    use Queueable;

    public string $title = '';
}
