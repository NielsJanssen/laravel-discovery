<?php

declare(strict_types=1);

use Benchmarks\Support\Guard;
use Benchmarks\Support\Php;
use Benchmarks\Support\Queries;
use Benchmarks\Support\Size;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if (! isset($argv[1])) {
    $failed = false;

    foreach (Size::cases() as $size) {
        $status = Php::run(Php::inheritedIni(), __FILE__, $size->value);
        $failed = $failed || $status !== 0;
    }

    exit($failed ? 1 : 0);
}

$size = Size::from($argv[1]);
$guard = new Guard($size);
$failures = $guard->check();

if ($failures !== []) {
    fwrite(STDERR, "Fairness guard failed:\n  " . implode("\n  ", $failures) . "\n");
    exit(1);
}

$operations = count(Queries::features()) + count(Queries::filler($size));
$counts = implode(', ', array_map(static fn(string $setup, int $count): string => "{$setup} {$count}", array_keys($guard->batchQueries), $guard->batchQueries));

echo sprintf("Fairness guard %s: identical SDL and %d identical responses across setups; batch query count: %s\n", $size->value, $operations, $counts);
