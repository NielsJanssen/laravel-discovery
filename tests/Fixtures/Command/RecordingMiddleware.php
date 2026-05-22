<?php

declare(strict_types=1);

namespace Tests\Fixtures\Command;

use Illuminate\Console\Command;
use NielsJanssen\Laravel\Discovery\Feature\Command\CommandMiddleware;

class RecordingMiddleware implements CommandMiddleware
{
    /** @var list<array{command: Command, invoked: bool}> */
    public static array $calls = [];

    public function __invoke(Command $command, callable $next): mixed
    {
        $index = count(self::$calls);

        self::$calls[] = ['command' => $command, 'invoked' => false];

        $result = $next();

        self::$calls[$index]['invoked'] = true;

        return $result;
    }
}
