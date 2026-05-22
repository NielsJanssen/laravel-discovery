<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Feature\Command\Middleware;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use NielsJanssen\Laravel\Discovery\Feature\Command\CommandMiddleware;
use NielsJanssen\Laravel\Discovery\Feature\Command\ProvidesInputOptions;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;

class Caution implements CommandMiddleware, ProvidesInputOptions
{
    public function getOptions(): array
    {
        return [
            new InputOption('force', 'f', InputOption::VALUE_NONE, 'Skip production confirmation'),
        ];
    }

    public function __invoke(Command $command, callable $next): mixed
    {
        if (App::isProduction() && ! $command->option('force')) {
            $env = App::environment();

            warning("⚠️  Application is in $env!");
            note('This command might be destructive for this environment. Please make sure you know what you are doing before continuing.');

            if (! confirm('Do you want to run this command?', default: false)) {
                return 1;
            }
        }

        return $next();
    }
}
