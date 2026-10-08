# Testing

A test suite rebuilds the application for every test, and outside a cached environment every boot scans your code
again. On a large codebase that scan dominates the run time. To scan once per run instead, add the
`WithCachedDiscovery` trait to your tests.

With Pest, apply it next to your base test case in `tests/Pest.php`:

```php
use NielsJanssen\Laravel\Discovery\Testing\WithCachedDiscovery;
use Tests\TestCase;

uses(TestCase::class, WithCachedDiscovery::class)->in('Feature');
```

With PHPUnit, use it on your base test case:

```php
namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use NielsJanssen\Laravel\Discovery\Testing\WithCachedDiscovery;

abstract class TestCase extends BaseTestCase
{
    use WithCachedDiscovery;
}
```

The first test boots and scans as usual, after which the trait keeps the result in the PHP process. Every later boot in
that process restores it instead of scanning your code, whatever `cache_environments` says, and the discovery locations
read from Composer's autoload files are reused as well. No environment variables or `discovery:cache` step are needed.
The cache disappears when the process ends: each run starts fresh, attribute changes are picked up on the next run, and
a run with `--parallel` gets one cache per worker.

Once the cache is filled it applies to every later boot in the process, including tests that do not use the trait. A
test that changes what discovery should find (a fixture class written at runtime, for instance) can drop the cached run,
so the next boot scans again:

```php
use NielsJanssen\Laravel\Discovery\Cache\MemoryAdapter;

MemoryAdapter::forget();
```

## The `memory` cache store

Before the trait existed, the same result came from enabling the cache for the `testing` environment with the `memory`
store in `phpunit.xml`:

```xml
<php>
    <env name="DISCOVERY_CACHE_ENVIRONMENTS" value="testing"/>
    <env name="DISCOVERY_CACHE_STORE" value="memory"/>
</php>
```

This still works, but the `memory` value of `cache_store` is deprecated and will be removed in the next major release.
Replace these variables with the trait.
