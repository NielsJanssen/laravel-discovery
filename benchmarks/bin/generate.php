<?php

declare(strict_types=1);

use Benchmarks\Generator\FixtureGenerator;
use Benchmarks\Support\Paths;
use Benchmarks\Support\Size;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if (! is_dir(Paths::opcache()) && ! mkdir(Paths::opcache(), 0o755, true)) {
    fwrite(STDERR, "Could not create the opcache directory.\n");
    exit(1);
}

foreach (Size::cases() as $size) {
    $files = new FixtureGenerator($size)->generate();
    echo sprintf("Generated %s: %d units, %d classes\n", $size->value, $size->units(), $files);
}
