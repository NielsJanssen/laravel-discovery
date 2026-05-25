<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Command\Middleware;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use NielsJanssen\Laravel\Discovery\Command\CommandMiddleware;

class Transaction implements CommandMiddleware
{
    /**
     * @throws \Throwable
     */
    public function __invoke(Command $command, callable $next): mixed
    {
        return DB::transaction(static fn() => $next());
    }
}
