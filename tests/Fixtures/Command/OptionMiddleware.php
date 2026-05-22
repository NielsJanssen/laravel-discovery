<?php

declare(strict_types=1);

namespace Tests\Fixtures\Command;

use Illuminate\Console\Command;
use NielsJanssen\Laravel\Discovery\Command\CommandMiddleware;
use NielsJanssen\Laravel\Discovery\Command\ProvidesInputOptions;
use Symfony\Component\Console\Input\InputOption;

class OptionMiddleware implements CommandMiddleware, ProvidesInputOptions
{
    public static ?string $tagValue = null;

    public function getOptions(): array
    {
        return [
            new InputOption('tag', null, InputOption::VALUE_OPTIONAL, 'A tag value'),
        ];
    }

    public function __invoke(Command $command, callable $next): mixed
    {
        self::$tagValue = $command->option('tag');

        return $next();
    }
}
