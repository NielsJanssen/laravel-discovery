<?php

declare(strict_types=1);

use Benchmarks\Support\BenchApp;
use Benchmarks\Support\Paths;
use Benchmarks\Support\Php;
use Benchmarks\Support\Setup;
use Benchmarks\Support\Size;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if (! isset($argv[1])) {
    foreach (Size::cases() as $size) {
        $status = Php::run(Php::inheritedIni(), __FILE__, $size->value);

        if ($status !== 0) {
            exit($status);
        }
    }

    exit(0);
}

$size = Size::from($argv[1]);
$cache = Paths::storage($size, Setup::Cached) . '/framework/cache/discovery';

if (is_dir($cache)) {
    passthru('rm -rf ' . escapeshellarg($cache));
}

$app = BenchApp::create(Setup::Cached, $size);

$kernel = $app->make(Kernel::class);
$status = $kernel->call('discovery:cache');

echo sprintf('Discovery cache for %s: %s', $size->value, trim($kernel->output())) . "\n";

exit($status);
