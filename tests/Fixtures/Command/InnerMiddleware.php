<?php

declare(strict_types=1);

namespace Tests\Fixtures\Command;

use Illuminate\Console\Command;
use NielsJanssen\Laravel\Discovery\Feature\Command\CommandMiddleware;

class InnerMiddleware implements CommandMiddleware
{
    public function __invoke(Command $command, callable $next): mixed
    {
        MiddlewareLog::$entries[] = 'inner:before';
        $result = $next();
        MiddlewareLog::$entries[] = 'inner:after';

        return $result;
    }
}
