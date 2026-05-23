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

Most projects don't need to touch the config. If you do, publish it:

```bash
php artisan vendor:publish --tag=discovery-config
```

The published config looks like this:

```php
return [
    'autoload' => base_path(),

    'skip_classes' => [],

    'skip_paths' => [],

    'cache_path' => 'framework/cache/discovery',

    'cache_environments' => ['production'],
];
```

### Configuration keys

| Key                  | Default                     | What it does                                                                                                                  |
|----------------------|-----------------------------|-------------------------------------------------------------------------------------------------------------------------------|
| `autoload`           | `base_path()`               | Directory containing the `composer.json` whose `autoload` PSR-4 namespaces will be scanned. Almost always your project root.  |
| `skip_classes`       | `[]`                        | Fully-qualified class names that discovery should ignore. Useful for opting individual classes out of scanning.               |
| `skip_paths`         | `[]`                        | Filesystem paths whose contents are skipped entirely. Add paths here if you want a whole directory tree ignored by discovery. |
| `cache_path`         | `framework/cache/discovery` | Storage-relative path used by the discovery cache. Resolved with `storage_path(...)`.                                         |
| `cache_environments` | `['production']`            | Environments where the discovery cache is active. In any other environment, classes are scanned fresh on each request.        |

## How discovery finds your code

When the service provider boots, it calls `DiscoveryConfig::autoload(base_path())`. That reads your project's
`composer.json` and scans:

1. The PSR-4 `autoload` namespaces declared in the root `composer.json` (your `App\` namespace, plus anything else
   you've added).
2. Every Composer package in `vendor/composer/installed.json` that requires a `tempest/*` package. This is how the
   package finds its own discovery classes.

A standard Laravel application with `App\` registered in `composer.json` works without further configuration. Additional
namespaces (`Domain\`, `Modules\Acme\`, etc.) declared alongside it are picked up automatically.

## Caching

The package ships with a discovery cache backed by Symfony's `PhpFilesAdapter`, so the filesystem scan does not run on
every request in production.

Generate the cache during deployment:

```bash
php artisan discovery:cache
```

`php artisan optimize` runs this too; the package registers itself with Laravel's optimizer.

Clear the cache (after deploying new attribute-tagged code locally, for example):

```bash
php artisan discovery:clear
```

By default the cache is only active in `production`. In other environments the package re-scans your code on every
request, so attribute changes are picked up immediately. To change that, set `discovery.cache_environments` to the list
of environments where the cache should be active:

```php
// config/discovery.php
'cache_environments' => ['production', 'staging'],
```

Running `discovery:cache` in an environment that isn't on this list prints a warning and exits without writing anything.

## Where to next

- [Commands](command.md), [Events](event.md), [Routes](router.md), [Schedule](schedule.md): the four built-in discovery
  systems.
- [Discovery internals](discovery.md): how Tempest Discovery works under the hood, and how to write your own.
