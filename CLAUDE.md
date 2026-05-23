# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**laravel-discovery** is a Laravel package that brings attribute-based discovery to Laravel applications using the Tempest Discovery library. It enables automatic registration of:
- **Commands**: Discover Artisan commands via attributes instead of service provider registration
- **Events**: Discover event listeners via attributes with automatic registration
- **Routes**: Discover routes via attributes with full support for HTTP method decorators and route modifiers
- **Schedules**: Discover scheduled tasks via attributes with cron expression or closure configuration

The package is built on top of Tempest Discovery (`tempest/discovery: ^3.10`) and integrates seamlessly with Laravel's service container, event dispatcher, router, and scheduler.

## Development Workflow

### Common Commands

```bash
composer install          # Install dependencies
composer test             # Run all tests
composer lint             # Lint code (Laravel Pint)
composer build            # Build workbench (test application)
composer serve            # Serve workbench locally
```

```bash
vendor/bin/pest tests/Feature/Feature/Command/CommandDiscoveryTest.php           # Single file
vendor/bin/pest tests/Feature/Feature/Command/CommandDiscoveryTest.php --filter=name  # Single test
vendor/bin/pest --ci      # CI mode (parallel, coverage)
```

## Code Architecture

### High-Level Architecture

The package implements four independent discovery systems that share a common pattern:

1. **Command Discovery** (`CommandDiscovery`): Finds and registers Artisan commands
2. **Event Discovery** (`EventDiscovery`): Finds and registers event listeners
3. **Route Discovery** (`RouteDiscovery`): Finds and registers HTTP routes
4. **Schedule Discovery** (`ScheduleDiscovery`): Finds and registers scheduled tasks

All discoverers implement Tempest's `Discovery` interface, use the `IsDiscovery` trait, and are found automatically when `BootDiscovery` scans the package's `src/` directory.

### Discovery Flow

1. `DiscoveryServiceProvider::boot()` calls `BootDiscovery`, storing returned discoveries in `config('discovery.discoveries')`
2. `BootDiscovery` first runs `DiscoveryDiscovery` to locate all `Discovery` implementations in the configured locations
3. Each discoverer's `discover()` is called for every class in every location, populating `$this->discoveryItems`
4. Each discoverer's `apply()` registers found items with Laravel's core systems
5. Caching is handled by `DiscoveryCache` via Symfony's `PhpFilesAdapter`

### How Discovery Finds the Package's Own Classes

`DiscoveryConfig::autoload(base_path())` reads the project's `composer.json` and scans:
- **Vendor packages** that require any `tempest/*` package (including `niels-janssen/laravel-discovery` itself, since it requires `tempest/discovery`)
- **App namespaces** defined in the root `composer.json`

For the test environment, the package is the root project (not in `vendor/composer/installed.json`), so `TestCase::defineEnvironment()` sets `discovery.autoload` to `dirname(__DIR__)` (project root) and explicitly appends the workbench app location.

### Configuration

`/config/discovery.php`:
```php
return [
    'autoload' => base_path(),              // Must point to a directory with composer.json
    'skip_classes' => [],
    'skip_paths' => [],
    'cache_path' => 'framework/cache/discovery',
    'cache_environments' => ['production'],
];
```

### Command Discovery System

**Files**: `/src/Command/*`

- **`ConsoleCommand` Attribute**: Marks a method as a discoverable Artisan command
- **`Command` Class**: Wraps discovered methods to integrate with Laravel's command system, resolving method parameters as CLI arguments, and running a middleware pipeline
- **`CommandArgumentsDefinition`**: Parses typed method parameters into Symfony input definitions; supports `ConsoleArgument` and `ConsoleOption` attributes
- **`CommandMiddleware` Interface**: Cross-cutting concerns; built-in: `Benchmark`, `Transaction`, `Caution`; middleware can implement `ProvidesInputOptions` to add custom CLI options

```php
class MyCommands
{
    #[ConsoleCommand('migrate:custom', 'Run migrations', middleware: [Benchmark::class])]
    public function migrate(
        #[ConsoleArgument] string $path,
        #[ConsoleOption(shortcut: 'f')] bool $fresh = false,
    ): void {}
}

// Plain Laravel commands (extend Illuminate\Console\Command) are also auto-discovered
```

### Event Discovery System

**Files**: `/src/Event/*`

- **`EventHandler` Attribute**: Marks a method as an event listener; event is inferred from the first parameter's type if not explicit
- Union-typed parameters register the listener for all class types in the union
- `deferred: true` delays registration until the listener class is resolved from the container

```php
class EventListeners
{
    #[EventHandler]
    public function onUserCreated(UserCreated $event): void {}

    #[EventHandler(event: [UserCreated::class, UserUpdated::class])]
    public function onUserEvent($event): void {}

    #[EventHandler(deferred: true)]
    public function onLazyEvent(SomeEvent $event): void {}
}
```

### Route Discovery System

**Files**: `/src/Router/*`

- **HTTP Method Attributes**: `Get`, `Post`, `Put`, `Patch`, `Delete`, `Head`, `Options`
- **Route Decorators**: `Prefix`, `Middleware`, `Domain` — applied class-level then method-level
- Routes can be repeated on a single method to register multiple URIs

```php
#[Prefix('/api')]
#[Middleware(['auth:api'])]
class UserController
{
    #[Get('/users')]
    public function index(): void {}

    #[Get('/users/{id}')]
    #[Get('/v2/users/{id}')]
    public function show(int $id): void {}
}
```

### Schedule Discovery System

**Files**: `/src/Schedule/*`

- **`Scheduled` Attribute**: Repeatable, targets methods; takes a single `$schedule` parameter that is `string|Frequency|\Closure`
- **`Frequency` Enum**: Backed string enum covering common intervals (`Frequency::Daily`, `Frequency::Hourly`, etc.)
- The `DiscoveredSchedule` DTO stores only `className`/`methodName` (no closures) so it can be cached; attributes are re-read from reflection in `apply()`
- Multiple `#[Scheduled]` on one method registers multiple events; auto-generated names get a `#N` suffix when names would collide

```php
class Tasks
{
    #[Scheduled('15 minutes')]
    public function syncData(): void {}

    #[Scheduled(Frequency::Daily)]
    public function generateReport(): void {}

    #[Scheduled(static function (Event $event) {
        $event->hourly()->withoutOverlapping()->onOneServer();
    })]
    #[Scheduled('5 minutes', name: 'quick-sync')]
    public function flexibleTask(): void {}
}
```

**Frequency string vocabulary**: `'minute'`, `'N minutes'`, `'hour'`/`'hourly'`, `'N hours'`, `'day'`/`'daily'`, `'week'`/`'weekly'`, `'month'`/`'monthly'`, `'quarter'`/`'quarterly'`, `'year'`/`'yearly'`.

## Testing Architecture

**Test Framework**: Pest (with PHPUnit as base)

**Workbench**: `/workbench/` — a minimal Laravel app for integration testing; configured via `testbench.yaml`.

**`TestCase::defineEnvironment()`** does two things that are critical for tests to work:
1. Sets `discovery.autoload` to `dirname(__DIR__)` (project root with `composer.json`) so `DiscoveryDiscovery` can find the package's own discovery classes
2. Appends `Workbench\App\` → `workbench/app/` as a `DiscoveryLocation` so workbench fixtures are scanned

**Test helpers**: `discoverCommands()`, `discoverRoutes()`, `registerRoutes()`, `discoverSchedule()` — each creates a fresh discoverer, runs `discover()` on specific fixture classes, then calls `apply()`.

**Singleton isolation**: Discoverers marked `#[Singleton]` are resolved once during boot. Feature tests that exercise a discoverer in isolation must reset both the discoverer and any Laravel singleton it wraps in `beforeEach`:
```php
beforeEach(function () {
    app()->forgetInstance(Schedule::class);
    app()->forgetInstance(ScheduleDiscovery::class);
});
```

## Built-in Artisan Commands

- **`php artisan discovery:cache`**: Generate discovery cache
- **`php artisan discovery:clear`**: Clear discovery cache
- **`php artisan make:discovery`**: Scaffold a new custom Discovery class

## Key Files

| File | Purpose |
|---|---|
| `/src/DiscoveryServiceProvider.php` | Boots discovery, registers `DiscoveryConfig` and `DiscoveryCache` singletons |
| `/src/Command/CommandDiscovery.php` | Discovers console commands via `#[ConsoleCommand]`; registers via `Artisan::starting()` |
| `/src/Event/EventDiscovery.php` | Discovers event listeners via `#[EventHandler]` |
| `/src/Router/RouteDiscovery.php` | Discovers routes via HTTP method attributes |
| `/src/Schedule/ScheduleDiscovery.php` | Discovers scheduled tasks via `#[Scheduled]` |
| `/src/Schedule/Scheduled.php` | `IS_REPEATABLE` attribute; `string\|Frequency\|\Closure` schedule parameter |
| `/src/Schedule/Frequency.php` | Backed enum of common schedule frequencies |
| `/src/Command/Command.php` | Wraps discovered methods as Laravel commands; runs middleware pipeline |
| `/src/Command/CommandArgumentsDefinition.php` | Parses method parameters into Symfony input definitions |
| `/config/discovery.php` | Package configuration |
| `/tests/TestCase.php` | Base test class; configures discovery locations for the test environment |

## Code Style & Standards

- PHP 8.5+ (closures-as-attribute-arguments are used in `#[Scheduled]`)
- Linter: Laravel Pint (`composer lint`)
- All files: `declare(strict_types=1);`
- Namespacing: PSR-4 via `NielsJanssen\Laravel\Discovery\*`

## CI/CD

**GitHub Actions** (`.github/workflows/ci.yml`): lint → test → audit → dependency review

**Release** (`.github/workflows/release.yml`): auto-changelog via `git-cliff`, semantic versioning via git tags
