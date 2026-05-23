# Schedule

Schedule tasks with an attribute. Methods carrying `#[Scheduled]` are registered with Laravel's scheduler at boot time.

```php
use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;
use NielsJanssen\Laravel\Discovery\Schedule\Frequency;

class Reports
{
    #[Scheduled(Frequency::Daily)]
    public function nightly(): void
    {
        // ...
    }
}
```

The task appears in `php artisan schedule:list` and fires every night at midnight when Laravel's scheduler is running.

## The `#[Scheduled]` attribute

```php
#[Scheduled(schedule: '15 minutes', name: 'sync-feed')]
```

| Parameter  | Type                                           | Purpose                                            |
|------------|------------------------------------------------|----------------------------------------------------|
| `schedule` | `string \| Frequency \| \Closure(Event): void` | The schedule. See below.                           |
| `name`     | `?string`                                      | Optional display name. Defaults to `Class@method`. |

The attribute is **repeatable** and **target-method only**. You can stack multiple `#[Scheduled]` attributes on a single
method to register it on multiple schedules at once.

## Three ways to schedule

### 1. The `Frequency` enum

Readable, self-documenting, autocompleted by your editor:

```php
#[Scheduled(Frequency::EveryFiveMinutes)]
public function poll(): void { /* ... */ }

#[Scheduled(Frequency::Hourly)]
public function refreshCache(): void { /* ... */ }

#[Scheduled(Frequency::Daily)]
public function nightlyReport(): void { /* ... */ }
```

All available cases:

| Case                             | Cron           |
|----------------------------------|----------------|
| `Frequency::EveryMinute`         | `* * * * *`    |
| `Frequency::EveryTwoMinutes`     | `*/2 * * * *`  |
| `Frequency::EveryThreeMinutes`   | `*/3 * * * *`  |
| `Frequency::EveryFourMinutes`    | `*/4 * * * *`  |
| `Frequency::EveryFiveMinutes`    | `*/5 * * * *`  |
| `Frequency::EveryTenMinutes`     | `*/10 * * * *` |
| `Frequency::EveryFifteenMinutes` | `*/15 * * * *` |
| `Frequency::EveryThirtyMinutes`  | `*/30 * * * *` |
| `Frequency::Hourly`              | `0 * * * *`    |
| `Frequency::EveryTwoHours`       | `0 */2 * * *`  |
| `Frequency::EveryThreeHours`     | `0 */3 * * *`  |
| `Frequency::EveryFourHours`      | `0 */4 * * *`  |
| `Frequency::EverySixHours`       | `0 */6 * * *`  |
| `Frequency::Daily`               | `0 0 * * *`    |
| `Frequency::Weekly`              | `0 0 * * 0`    |
| `Frequency::Monthly`             | `0 0 1 * *`    |
| `Frequency::Quarterly`           | `0 0 1 */3 *`  |
| `Frequency::Yearly`              | `0 0 1 1 *`    |

### 2. A frequency string

For one-off intervals not in the enum:

```php
#[Scheduled('7 minutes')]
public function poll(): void { /* ... */ }

#[Scheduled('hourly')]
public function refresh(): void { /* ... */ }
```

Recognized strings (case-sensitive):

- `'minute'`
- `'N minutes'`: any positive integer, e.g. `'7 minutes'`, `'45 minutes'`
- `'hour'`, `'hourly'`
- `'N hours'`
- `'day'`, `'daily'`
- `'week'`, `'weekly'`
- `'month'`, `'monthly'`
- `'quarter'`, `'quarterly'`
- `'year'`, `'yearly'`

Anything else throws `InvalidArgumentException` at boot, so you find typos immediately.

### 3. A closure

When you need the full power of Laravel's `Event` builder (timezones, overlap protection, single-server execution,
environment filters), pass a closure:

```php
use Illuminate\Console\Scheduling\Event;

#[Scheduled(static function (Event $event) {
    $event
        ->hourly()
        ->withoutOverlapping()
        ->onOneServer()
        ->timezone('Europe/Amsterdam');
})]
public function importantJob(): void
{
    // ...
}
```

The closure is called with the `Event` instance Laravel created for the schedule. Anything you can do with the chained
builder in `routes/console.php` works here.

## Multiple schedules per method

Stack `#[Scheduled]` attributes when you want the same handler to run on different schedules:

```php
class Sync
{
    #[Scheduled('5 minutes', name: 'fast-sync')]
    #[Scheduled(Frequency::Daily, name: 'nightly-full-sync')]
    public function run(): void
    {
        // ...
    }
}
```

When you don't supply a name on stacked attributes, generated names get a `#0`, `#1`, ... suffix to avoid collisions:

```php
#[Scheduled('15 minutes')]
#[Scheduled('1 hour')]
public function check(): void {}

// Registered as:
//   App\Sync@check#0
//   App\Sync@check#1
```

## Naming

```php
#[Scheduled(Frequency::Hourly, name: 'order-reconciliation')]
```

Names show up in `php artisan schedule:list` and are used by features like `withoutOverlapping` and `onOneServer` to
track running tasks. Pick a stable, descriptive name if those matter to you.

If you omit the name, the default is `Class@method` (with a `#N` suffix when stacked).

## Dependency injection

Scheduled methods are invoked through the container, so type-hinted dependencies are resolved automatically:

```php
#[Scheduled(Frequency::Hourly)]
public function reconcile(OrderRepository $orders, Notifier $notifier): void
{
    // ...
}
```

The class itself is also resolved from the container, so constructor injection works the same way.

## Putting it all together

```php
namespace App\Maintenance;

use App\Services\Reports;
use Illuminate\Console\Scheduling\Event;
use NielsJanssen\Laravel\Discovery\Schedule\Frequency;
use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;

class Cleanup
{
    public function __construct(private Reports $reports) {}

    #[Scheduled(Frequency::EveryFiveMinutes, name: 'heartbeat')]
    public function heartbeat(): void
    {
        // ping the monitoring service
    }

    #[Scheduled('30 minutes')]
    public function pruneSessions(): void
    {
        // ...
    }

    #[Scheduled(static function (Event $event) {
        $event->dailyAt('03:30')->onOneServer()->withoutOverlapping(60);
    }, name: 'nightly-report')]
    public function generateReport(): void
    {
        $this->reports->build();
    }
}
```
