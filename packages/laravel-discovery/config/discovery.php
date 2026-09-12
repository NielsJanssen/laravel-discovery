<?php

declare(strict_types=1);

return [
    'autoload' => base_path(),

    'skip_classes' => [],

    'skip_paths' => [],

    'cache_path' => 'framework/cache/discovery',

    'cache_environments' => explode(',', (string) env('DISCOVERY_CACHE_ENVIRONMENTS', 'production')),

    /*
     * Where a cached discovery run is kept.
     *
     * "files"  writes to cache_path, survives between processes, and is filled by `discovery:cache`.
     * "memory" keeps the cache in the PHP process and fills itself on the first boot, so a test
     *          suite scans once and every later test in that run reuses the result.
     */
    'cache_store' => env('DISCOVERY_CACHE_STORE', 'files'),
];
