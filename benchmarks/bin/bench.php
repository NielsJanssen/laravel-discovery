<?php

declare(strict_types=1);

use Benchmarks\Support\Paths;
use Benchmarks\Support\Php;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$steps = [
    [[], __DIR__ . '/generate.php'],
    [[], __DIR__ . '/prepare.php'],
    [Php::benchIni(), __DIR__ . '/guard.php'],
];

foreach ($steps as [$ini, $script]) {
    $status = Php::run($ini, $script);

    if ($status !== 0) {
        exit($status);
    }
}

$arguments = isset($argv) ? array_slice($argv, 1) : [];

exit(Php::run([], Paths::root() . '/vendor/bin/phpbench', 'run', '--php-config=' . json_encode(Php::benchIni(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), ...($arguments === [] ? ['--report=setups', '--report=variants'] : $arguments)));
