<?php

declare(strict_types=1);

namespace Tests\Fixtures\Command;

use Illuminate\Console\Command;
use NielsJanssen\Laravel\Discovery\Command\CommandMiddleware;

class OuterMiddleware implements CommandMiddleware
{
    public function __invoke(Command $command, callable $next): mixed
    {
        MiddlewareLog::$entries[] = 'outer:before';
        $result = $next();
        MiddlewareLog::$entries[] = 'outer:after';

        return $result;
    }
}
