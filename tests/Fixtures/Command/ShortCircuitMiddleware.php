<?php

declare(strict_types=1);

namespace Tests\Fixtures\Command;

use Illuminate\Console\Command;
use NielsJanssen\Laravel\Discovery\Feature\Command\CommandMiddleware;

class ShortCircuitMiddleware implements CommandMiddleware
{
    public function __invoke(Command $command, callable $next): mixed
    {
        // Intentionally does not call $next(); short-circuits the pipeline
        return 42;
    }
}
