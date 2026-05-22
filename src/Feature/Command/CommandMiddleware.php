<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Feature\Command;

interface CommandMiddleware
{
    public function __invoke(object $command, callable $next): mixed;
}
