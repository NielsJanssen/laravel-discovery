<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Feature\Command;

use Illuminate\Console\Command;

interface CommandMiddleware
{
    public function __invoke(Command $command, callable $next): mixed;
}
