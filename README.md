<img src="assets/discovery-horizontal.svg" alt="Discovery for Laravel" width="540">

<br>

Bring [Tempest Discovery](https://tempestphp.com/) to Laravel. Register commands, event listeners, routes, and scheduled
tasks with attributes instead of editing your service providers.

```php
class Tasks
{
    #[ConsoleCommand('users:cleanup', 'Remove stale user accounts')]
    public function cleanup(int $days = 30): void
    {
        // ...
    }

    #[Scheduled(Every::Day)]
    public function runNightlyReports(): void
    {
        // ...
    }
}
```

The command appears in `php artisan list`, and the scheduled task runs every night. No service provider changes are
required.

## Features

- **Commands**: register Artisan commands with `#[ConsoleCommand]`, with typed argument and option resolution and
  pluggable middleware (benchmark, transaction, production-confirm).
- **Events**: wire listeners with `#[EventHandler]`. The package infers the event class from your method signature.
- **Routes**: declare HTTP routes with `#[Get]`, `#[Post]`, and friends. Group them with `#[Prefix]`, `#[Middleware]`,
  and `#[Domain]`.
- **Schedule**: schedule methods with `#[Scheduled]` using the `Every` enum, a raw `Cron` expression, or a closure for
  full control.
- **Custom discovery**: use the same machinery for your own patterns. `php artisan make:discovery` scaffolds one.
- **Caching**: production-ready discovery cache via `php artisan discovery:cache` (and `php artisan optimize`).

## Requirements

- PHP 8.5+
- Laravel 13+
- `tempest/discovery` ^3.10 (installed automatically)

## Installation

```bash
composer require nielsjanssen/laravel-discovery
```

The service provider registers itself via Laravel's package discovery, so there is nothing to add to `config/app.php`.

See [docs/installation.md](docs/installation.md) for configuration and caching.

## How it works

You add attributes to methods on your classes. When the application boots, the package scans your autoloaded code, reads
those attributes, and registers each one with the matching Laravel system: Artisan, the event dispatcher, the router, or
the scheduler.

```text
app/                                Laravel reads attributes ->
  Inventory/                        Tempest Discovery scans  ->
    Tasks.php                       laravel-discovery applies ->
    Routes.php                      commands, listeners, routes,
    Reports.php                     and schedules are registered
```

You write one class per concern (or grouped however you like), and never have to register it in a second place.

## Quick start

```php
use NielsJanssen\Laravel\Discovery\Command\ConsoleCommand;
use NielsJanssen\Laravel\Discovery\Event\EventHandler;
use NielsJanssen\Laravel\Discovery\Router\Get;
use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;
use NielsJanssen\Laravel\Discovery\Schedule\Every;

class Inventory
{
    #[Get('/inventory')]
    public function index(): array
    {
        return [/* ... */];
    }

    #[ConsoleCommand('inventory:rebuild', 'Rebuild the inventory index')]
    public function rebuild(): void
    {
        // ...
    }

    #[EventHandler]
    public function onStockUpdated(StockUpdated $event): void
    {
        // ...
    }

    #[Scheduled(Every::Hour)]
    public function reconcile(): void
    {
        // ...
    }
}
```

After booting, `php artisan list`, `php artisan route:list`, and `php artisan schedule:list` will each show the
registered handlers.

## Documentation

- [Installation](docs/installation.md): install, configure, cache.
- [Commands](docs/command.md): `#[ConsoleCommand]`, arguments, options, middleware.
- [Events](docs/event.md): `#[EventHandler]`, inferred event types, deferred listeners.
- [Routes](docs/router.md): HTTP attributes and class-level decorators.
- [Schedule](docs/schedule.md): `#[Scheduled]`, the `Every` enum, `Cron`, time windows, overlap constraints.
- [Discovery internals](docs/discovery.md): how Tempest Discovery is wired into Laravel, and how to write your own.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Bug reports, feature requests, and PRs welcome.

## License

MIT. See [composer.json](composer.json).

## Credits

Built on top of [Tempest Discovery](https://tempestphp.com/) by the Tempest team. This package wires it into Laravel;
the underlying scanning engine is theirs.
