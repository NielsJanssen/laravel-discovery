# Installation

## Requirements

- PHP 8.5+
- Laravel 13+

## Install via Composer

```bash
composer require nielsjanssen/laravel-discovery
```

Laravel auto-discovers the service provider (`NielsJanssen\Laravel\Discovery\DiscoveryServiceProvider`) via the
`extra.laravel.providers` entry in the package's `composer.json`, so no manual registration is required.

To verify the install, run `php artisan list`. You should see `discovery:cache`, `discovery:clear`, and `make:discovery`
in the available commands.

## Publish the configuration (optional)

Most projects don't need to touch the config. If you do, you can publish it like so:

```bash
php artisan vendor:publish --tag=discovery-config
```

### Configuration keys

| Key                  | Default                     | What it does                                                                                                                  |
|----------------------|-----------------------------|-------------------------------------------------------------------------------------------------------------------------------|
| `autoload`           | `base_path()`               | Directory containing the `composer.json` whose `autoload` PSR-4 namespaces will be scanned. Almost always your project root.  |
| `skip_classes`       | `[]`                        | Fully-qualified class names that discovery should ignore. Useful for opting individual classes out of scanning.               |
| `skip_paths`         | `[]`                        | Filesystem paths whose contents are skipped entirely. Add paths here if you want a whole directory tree ignored by discovery. |
| `cache_path`         | `framework/cache/discovery` | Storage-relative path used by the discovery cache. Resolved with `storage_path(...)`.                                         |
| `cache_environments` | `['production']`            | Environments where the discovery cache is active, overridable with `DISCOVERY_CACHE_ENVIRONMENTS` (comma separated). In any other environment, classes are scanned fresh on each request. |
| `cache_store`        | `files`                     | Where a cached run is kept: `files` writes to `cache_path`, `memory` keeps it in the PHP process. Overridable with `DISCOVERY_CACHE_STORE`. |

## How discovery finds your code

When the service provider boots, it calls `DiscoveryConfig::autoload(base_path())`. That reads your project's
`composer.json` and scans all PSR-4 namespaces registered with composer, so your own code and any third-party packages
you've installed. It then looks through all classes in those namespaces and runs discovery on them. Each discovery
step will look for specific attributes or interfaces and register things accordingly (commands, event listeners, routes,
etc.).

## Caching

Discovery can be a heavy process if you have a large codebase, so it is recommended to cache the results in production.
With caching enabled Discovery doesn't perform any heavy operations at runtime, performing similar to a standard Service
Provider.

Generate the cache during deployment:

```bash
php artisan discovery:cache

# or

php artisan optimize
```

Clear the cache (after deploying new attribute-tagged code locally, for example):

```bash
php artisan discovery:clear
```

By default, the cache is only active in `production`. In other environments the package re-scans your code on every
request, so attribute changes are picked up immediately. To change that, set `discovery.cache_environments` to the list
of environments where the cache should be active:

```php
// config/discovery.php
'cache_environments' => ['production', 'staging'],
```

Running `discovery:cache` in an environment that isn't on this list prints a warning and exits without writing anything.

### Caching a test run

A test suite rebuilds the application for every test, so it scans your code once per test. Setting the cache store to
`memory` keeps one cached run in the PHP process: the first boot scans and fills the cache, every later boot in that
process restores it, and the cache disappears when the process ends. Each run therefore starts fresh, and a run with
`--parallel` gets one cache per worker.

```xml
<!-- phpunit.xml.dist -->
<env name="DISCOVERY_CACHE_STORE" value="memory"/>
<env name="DISCOVERY_CACHE_ENVIRONMENTS" value="production,testing"/>
```

The in-memory store fills itself, so there is no `discovery:cache` step to run. The file store never fills itself on
boot, which leaves a deployment in control of when it is written.

A test that changes what discovery should find (a fixture class written at runtime, for instance) can start over with
`DiscoveryServiceProvider::forgetProcessCache()`.

## Where to next

- [Commands](command.md), [Events](event.md), [Routes](router.md), [Schedule](schedule.md): the four built-in discovery
  systems.
- [Discovery internals](discovery.md): how Tempest Discovery works under the hood, and how to write your own.
