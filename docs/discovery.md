# Discovery internals

This page covers what Tempest Discovery is, how `laravel-discovery` wires it into Laravel, and how to write your own
discovery class for project-specific patterns. The other docs cover day-to-day use; this one is for when you want to
understand the machinery or extend it.

## What is Tempest Discovery?

[Tempest Discovery](https://tempestphp.com/) is a small, standalone library that scans your project's autoloaded classes
and lets "discovery classes" react to each one. It's the engine behind much of Tempest's framework convention: routes,
events, console commands, and so on.

The contract is one interface:

```php
namespace Tempest\Discovery;

interface Discovery
{
    public function discover(DiscoveryLocation $location, ClassReflector $class): void;
    public function apply(): void;
}
```

- `discover()` is called once per class in every scanned location. You decide if and how to remember the class.
- `apply()` is called once per discovery class, after all classes have been scanned. This is where you do something with
  what you found.

Discovered items are typically stashed in `$this->discoveryItems` (provided by the `IsDiscovery` trait) so they survive
between `discover()` and `apply()`, and can be serialized to a cache.

## How this package wires it into Laravel

The flow lives in `DiscoveryServiceProvider`:

1. **`register()`** binds two singletons:
    - `DiscoveryConfig`: built from `config('discovery.autoload')` plus skip lists.
    - `DiscoveryCache`: backed by Symfony's `PhpFilesAdapter`, writing to `storage/framework/cache/discovery`. The
      strategy is `FULL` in cache-enabled environments and `NONE` everywhere else.
    - Also registers `discovery:cache` / `discovery:clear` with `optimizes()` so they hook into `php artisan optimize`.
2. **`boot()`** calls `BootDiscovery::class` through the container. Tempest does the actual scanning:
    - Reads `composer.json` files (project + vendor packages requiring `tempest/*`).
    - Walks every PSR-4 namespace, instantiating a `ClassReflector` for each class.
    - Hands each class to every registered `Discovery` implementation's `discover()` method.
    - Finally calls each discovery's `apply()`.
3. The list of discovery class names is stored in `config('discovery.discovery_classes')` so the cache command can
   rebuild them on demand.

Conveniently, `BootDiscovery` first runs `DiscoveryDiscovery`, a discovery whose only job is to find all `Discovery`
implementations. Drop a class implementing `Discovery` anywhere in your discoverable namespaces and it gets picked up.

## What gets scanned

`DiscoveryConfig::autoload(base_path())` (the default) walks:

1. **The root `composer.json`**: every PSR-4 namespace declared in `autoload.psr-4`.
2. **Every vendor package** in `vendor/composer/installed.json` whose `require` includes a `tempest/*` package. This is
   how `laravel-discovery` itself is found: it depends on `tempest/discovery`.

The second point is the package-discovery mechanism: a third-party package opts into being scanned by depending on a
Tempest package. A library that ships `#[ConsoleCommand]`-annotated classes will have its commands discovered
automatically by any consumer that also installs this package.

If you need to scan additional paths, you can add `DiscoveryLocation` entries programmatically in a service provider.
(The `TestCase` does this; see `tests/TestCase.php`.)

## Skipping things

Two knobs in `config/discovery.php`:

```php
'skip_classes' => [
    App\Internal\TopSecret::class,
],

'skip_paths' => [
    base_path('legacy/'),
],
```

- `skip_classes` lists fully-qualified class names. Useful when a single class is causing trouble.
- `skip_paths` lists filesystem paths whose contents are never opened. Cheaper than `skip_classes` when you want to
  exclude a whole directory tree.

Both lists are empty by default. To opt a single class out from its own source file, use Tempest's `#[SkipDiscovery]`
attribute:

```php
use Tempest\Discovery\SkipDiscovery;

#[SkipDiscovery]
class HelperClass { /* not scanned */ }
```

## Caching strategy

The user-facing commands and configuration for the discovery cache are covered
in [Installation › Caching](installation.md#caching). This section is about what those commands actually do.

The cache writes one PHP file per entry under `storage/framework/cache/discovery`. On subsequent boots, Tempest reads
these files instead of scanning the filesystem. The cache is environment-gated: in environments not listed under
`discovery.cache_environments`, the strategy is set to `DiscoveryCacheStrategy::NONE`, so any existing cache file is
ignored even if it is on disk.

That is why running `discovery:cache` in an environment not on the list is a no-op: the resulting file would never be
read.

## Writing your own discovery

For project-specific patterns that would benefit from "find every class that does X", you can write your own discovery
class. The fastest way to scaffold one:

```bash
php artisan make:discovery EventBusDiscovery
```

That scaffolds a class at `app/Discoveries/EventBusDiscovery.php`:

```php
namespace App\Discoveries;

use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Reflection\ClassReflector;

final class EventBusDiscovery implements Discovery
{
    use IsDiscovery;

    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (! $class->implements('MyClass::class')) {
            return;
        }

        $this->discoveryItems->add($location, $class);
    }

    public function apply(): void
    {
        foreach ($this->discoveryItems as $class) {
            // Do something with the discovered class
        }
    }
}
```

Fill in the details:

```php
namespace App\Discoveries;

use App\EventBus\Bus;
use App\EventBus\Handler;
use Illuminate\Container\Attributes\Singleton;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Reflection\ClassReflector;

#[Singleton]
final class EventBusDiscovery implements Discovery
{
    use IsDiscovery;

    public function __construct(private Bus $bus) {}

    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (! $class->isInstantiable()) {
            return;
        }

        if (! $class->implements(Handler::class)) {
            return;
        }

        $this->discoveryItems->add($location, $class->getName());
    }

    public function apply(): void
    {
        foreach ($this->discoveryItems as $className) {
            $this->bus->register($className);
        }
    }
}
```

A few things worth knowing:

- **Constructor injection works.** The discovery class is resolved through Laravel's container, so type-hinted
  dependencies (the `Bus` above) are wired automatically.
- **`#[Singleton]`** keeps the discovery in the container as a single instance. Useful when you want to read or mutate
  state outside discovery (the package's own `CommandDiscovery` and `ScheduleDiscovery` do this).
- **`#[Scoped]`** is an alternative to `#[Singleton]` when you want per-request lifetimes. `RouteDiscovery` uses it.
- **Be careful what you put in `discoveryItems`.** The contents need to be serializable for the cache to work. Stick to
  scalars, arrays, and your own simple DTOs. If you need reflection or closures, store the class/method name and rebuild
  them in `apply()`. See `DiscoveredSchedule` in this package for a working example.
- **`apply()` runs at boot.** Anything you do there will be invoked on every request, so keep it light. Register
  listeners and routes; don't perform expensive setup.

## The reflection API

Tempest's reflection wrappers are richer than PHP's built-ins. A few things you'll use often:

- `$class->isInstantiable()`: skip abstract classes, interfaces, and traits.
- `$class->is(SomeClass::class)`: `true` if the class is or extends `SomeClass`.
- `$class->implements(SomeInterface::class)`: `true` if the class implements the interface.
- `$class->getAttribute(SomeAttribute::class)`: get a single attribute instance, or `null`.
- `$class->getAttributes(SomeAttribute::class)`: get all instances (for repeatable attributes).
- `$class->getPublicMethods()`: iterate `MethodReflector`s.
- `$method->getAttribute(...)` / `$method->getAttributes(...)`: same as the class versions but on methods.
- `$method->getParameters()`: yields `ParameterReflector`s.
- `$parameter->getType()`: yields `TypeReflector`, with helpful `isScalar()`, `isClass()`, `isUnion()`, `split()`, etc.

## Conventions used in this package

A few patterns you'll see in the source that are worth borrowing:

- **Store reflectors in `discover()`, not at `apply()` time.** Tempest's discovery flow only gives you the
  `ClassReflector` during `discover()`. After that, you have access to whatever you stashed in `discoveryItems`.
- **For cached discoveries, store strings (class/method names) instead of reflectors.** Reflectors aren't always
  cacheable. Then re-reflect in `apply()`. `ScheduleDiscovery` is the canonical example: it stores `DiscoveredSchedule`
  DTOs with just `className`/`methodName` strings, and uses `ReflectionMethod` in `apply()` to read the closures back
  from the live attributes.
- **Honor framework caching.** If Laravel has already cached its own state (routes, config), check for that and bail
  out. `RouteDiscovery::apply()` does exactly this with `$app->routesAreCached()`.

## When not to use discovery

Discovery is fantastic for class-level conventions. It's a worse fit when:

- You only have one or two of something, and registering them by hand is two lines.
- The thing you're registering is dynamic and depends on runtime data (e.g., the user's session). Discovery runs once at
  boot.
- You need a specific load order. Discovery doesn't guarantee one; write a service provider with explicit `register()`
  calls if order matters.

## Reference

| File                                    | Purpose                                                      |
|-----------------------------------------|--------------------------------------------------------------|
| `src/DiscoveryServiceProvider.php`      | Wires Tempest into Laravel and registers the optimize hooks. |
| `src/Laravel/DiscoveryCacheCommand.php` | The `discovery:cache` and `discovery:clear` commands.        |
| `src/Laravel/MakeDiscoveryCommand.php`  | The `make:discovery` generator.                              |
| `stubs/Discovery.stub`                  | The stub used by `make:discovery`.                           |
| `config/discovery.php`                  | Package configuration.                                       |
