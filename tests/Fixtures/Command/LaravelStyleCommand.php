<?php

declare(strict_types=1);

namespace Tests\Fixtures\Command;

use Illuminate\Console\Command;

class LaravelStyleCommand extends Command
{
    protected $signature = 'fixture:laravel';

    protected $description = 'A plain Laravel-style command';

    public function handle(): void {}
}
