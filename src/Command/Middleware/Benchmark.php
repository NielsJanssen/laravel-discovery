<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Command\Middleware;

use Illuminate\Console\Command;
use Illuminate\Support\Benchmark as LaravelBenchmark;
use NielsJanssen\Laravel\Discovery\Command\CommandMiddleware;

class Benchmark implements CommandMiddleware
{
    /**
     * @throws \Throwable
     */
    public function __invoke(Command $command, callable $next): mixed
    {
        [$result, $time] = LaravelBenchmark::value(static fn() => $next());

        $command->getOutput()->writeln('');
        $command->getOutput()->writeln(sprintf('<fg=gray>Command finished in %.2fms</>', $time));

        return $result;
    }
}
