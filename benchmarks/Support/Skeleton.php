<?php

declare(strict_types=1);

namespace Benchmarks\Support;

use Orchestra\Testbench\Foundation\Application;

final class Skeleton extends Application
{
    /** Uses Testbench's bare skeleton rather than the workbench's bootstrap file and providers. */
    protected function getApplicationBootstrapFile(string $filename): string|false
    {
        return false;
    }
}
