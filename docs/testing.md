# Testing

A test suite rebuilds the application for every test, and outside a cached environment every boot scans your code
again. On a large codebase that scan dominates the run time. To scan once per run instead, enable the cache for the
`testing` environment and keep it in memory:

```xml
<!-- phpunit.xml -->
<php>
    <env name="DISCOVERY_CACHE_ENVIRONMENTS" value="testing"/>
    <env name="DISCOVERY_CACHE_STORE" value="memory"/>
</php>
```

`DISCOVERY_CACHE_ENVIRONMENTS` turns the cache on while the tests run; the variable only applies to the test process, so
your `production` setting is unaffected. `DISCOVERY_CACHE_STORE=memory` keeps the cached run in the PHP process instead
of on disk: the first boot scans and fills it, every later boot in that process restores it, and it disappears when the
process ends. Each run therefore starts fresh, so attribute changes are picked up on the next run, and a run with
`--parallel` gets one cache per worker.

The memory store fills itself, so there is no `discovery:cache` step to run before your tests. The `files` store never
fills itself on boot, which leaves a deployment in control of when it is written; leave it out of your test setup.

A test that changes what discovery should find (a fixture class written at runtime, for instance) can drop the cached
run and start over:

```php
use NielsJanssen\Laravel\Discovery\Cache\MemoryAdapter;

MemoryAdapter::forget();
```
