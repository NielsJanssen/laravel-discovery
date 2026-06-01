# Schedule

Schedule tasks with an attribute. Methods carrying `#[Scheduled]` are registered with Laravel's scheduler at boot time.

```php
use NielsJanssen\Laravel\Discovery\Schedule\Every;
use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;

class Reports
{
    #[Scheduled(Every::Day)]
    public function nightly(): void
    {
        // ...
    }
}
```

The task appears in `php artisan schedule:list` and fires every night at midnight when Laravel's scheduler is running.

## The `#[Scheduled]` attribute

| Parameter            | Type                                          | Default  | Purpose                                              |
|----------------------|-----------------------------------------------|----------|------------------------------------------------------|
| `schedule`           | `Cron\|Every\|\Closure(Event): void`          | required | The schedule. See below.                             |
| `between`            | `?BetweenTime`                                | `null`   | Only run within this time window.                    |
| `unlessBetween`      | `?BetweenTime`                                | `null`   | Skip runs that fall within this time window.         |
| `withoutOverlapping` | `bool`                                        | `false`  | Prevent a new run while a previous one is still running. |
| `onOneServer`        | `bool`                                        | `false`  | Limit execution to a single server in a multi-server setup. |
| `timezone`           | `?string`                                     | `null`   | Run the task in this timezone.                       |
| `name`               | `?string`                                     | `null`   | Display name. Defaults to `Class@method`.            |

The attribute is **repeatable** and targets both methods and command classes. Stack multiple `#[Scheduled]` attributes on a single method to register it on several schedules at once. Applied to an Artisan command class, a single `#[Scheduled]` schedules the command itself; see [Scheduling a command](#scheduling-a-command).

## Three ways to schedule

### 1. The `Every` enum

Readable, self-documenting, autocompleted by your editor:

```php
use NielsJanssen\Laravel\Discovery\Schedule\Every;

#[Scheduled(Every::FiveMinutes)]
public function poll(): void { /* ... */ }

#[Scheduled(Every::Hour)]
public function refreshCache(): void { /* ... */ }

#[Scheduled(Every::Day)]
public function nightlyReport(): void { /* ... */ }
```

**Standard cases:**

| Case                    | Cron           |
|-------------------------|----------------|
| `Every::Minute`         | `* * * * *`    |
| `Every::TwoMinutes`     | `*/2 * * * *`  |
| `Every::ThreeMinutes`   | `*/3 * * * *`  |
| `Every::FourMinutes`    | `*/4 * * * *`  |
| `Every::FiveMinutes`    | `*/5 * * * *`  |
| `Every::TenMinutes`     | `*/10 * * * *` |
| `Every::FifteenMinutes` | `*/15 * * * *` |
| `Every::ThirtyMinutes`  | `*/30 * * * *` |
| `Every::Hour`           | `0 * * * *`    |
| `Every::TwoHours`       | `0 */2 * * *`  |
| `Every::ThreeHours`     | `0 */3 * * *`  |
| `Every::FourHours`      | `0 */4 * * *`  |
| `Every::SixHours`       | `0 */6 * * *`  |
| `Every::Day`            | `0 0 * * *`    |
| `Every::Week`           | `0 0 */7 * *`  |
| `Every::Month`          | `0 0 1 * *`    |
| `Every::Quarter`        | `0 0 1 */3 *`  |
| `Every::Year`           | `0 0 1 1 *`    |

**Sub-minute cases:**

Sub-minute cases combine a `* * * * *` base cron with Laravel's sub-minute scheduling via `repeatEvery()`.

| Case                    | Repeat interval |
|-------------------------|-----------------|
| `Every::Second`         | 1 second        |
| `Every::TwoSeconds`     | 2 seconds       |
| `Every::FiveSeconds`    | 5 seconds       |
| `Every::TenSeconds`     | 10 seconds      |
| `Every::FifteenSeconds` | 15 seconds      |
| `Every::TwentySeconds`  | 20 seconds      |
| `Every::ThirtySeconds`  | 30 seconds      |

### 2. A raw cron expression

Use `Cron` when you need a specific schedule that the `Every` enum does not cover, such as time-of-day precision:

```php
use NielsJanssen\Laravel\Discovery\Schedule\Cron;

#[Scheduled(new Cron('30 6 * * 1'))]
public function weeklyDigest(): void
{
    // runs every Monday at 06:30
}
```

The expression is passed directly to Laravel's `Event::cron()` without modification.

### 3. A closure (advanced)

The closure option is the escape hatch for scheduling requirements that the declarative API cannot express. Use it for:

- Environment-restricted execution (`$event->environments(['production'])`)
- Running during maintenance mode (`$event->evenInMaintenanceMode()`)
- Background process forking (`$event->runInBackground()`)
- Dynamic conditions (`$event->when(fn() => ...)`)
- Lifecycle hooks (`$event->onSuccess(...)`, `$event->onFailure(...)`, `$event->pingBefore(...)`)

```php
use Illuminate\Console\Scheduling\Event;

#[Scheduled(static function (Event $event) {
    $event
        ->dailyAt('03:30')
        ->environments(['production'])
        ->onSuccess(fn() => Heartbeat::ping());
})]
public function generateReport(): void
{
    // ...
}
```

The closure receives the `Event` instance Laravel created for the task. Timezone, overlap prevention, and server constraints do not need a closure; use the `#[Scheduled]` parameters or the [schedule decorators](#schedule-decorators) instead.

## Scheduling a command

Apply `#[Scheduled]` to a class that extends `Illuminate\Console\Command` to schedule the Artisan command itself:

```php
use Illuminate\Console\Command;
use NielsJanssen\Laravel\Discovery\Schedule\Every;
use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;

#[Scheduled(Every::Day)]
class PruneStaleReservations extends Command
{
    protected $signature = 'inventory:prune-reservations';

    protected $description = 'Release inventory reservations that were never checked out.';

    public function handle(): void
    {
        // ...
    }
}
```

## Time windows

`between` and `unlessBetween` accept a `BetweenTime` instance and restrict when the task is allowed to run. The underlying cron still fires at the configured interval; the time window just determines whether each execution is skipped.

```php
use NielsJanssen\Laravel\Discovery\Schedule\BetweenTime;

#[Scheduled(Every::Hour, between: new BetweenTime('08:00', '17:00'))]
public function syncOrders(): void
{
    // only runs during business hours
}

#[Scheduled(Every::FifteenMinutes, unlessBetween: new BetweenTime('22:00', '06:00'))]
public function pollFeed(): void
{
    // skipped overnight
}
```

For dynamic conditions, use a closure instead.

## Schedule decorators

The `#[Timezone]`, `#[WithoutOverlapping]`, and `#[OnOneServer]` attributes are schedule decorators. Applied to a class, they set a default for every `#[Scheduled]` method in that class. Applied to a method, they override any class-level default for that method alone.

```php
use NielsJanssen\Laravel\Discovery\Schedule\Every;
use NielsJanssen\Laravel\Discovery\Schedule\OnOneServer;
use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;
use NielsJanssen\Laravel\Discovery\Schedule\Timezone;
use NielsJanssen\Laravel\Discovery\Schedule\WithoutOverlapping;

#[Timezone('Europe/Amsterdam')]
#[WithoutOverlapping]
#[OnOneServer]
class Maintenance
{
    #[Scheduled(Every::FiveMinutes)]
    public function heartbeat(): void { /* ... */ }

    #[Scheduled(Every::Day)]
    public function cleanup(): void { /* ... */ }
}
```

Both methods inherit the class-level timezone, overlap prevention, and single-server constraint. A method-level decorator overrides the class-level one for that method only.

The same decorators are also valid on individual methods when there is no class-level default:

```php
#[Scheduled(Every::Hour)]
#[Timezone('Europe/Paris')]
public function processOrders(): void { /* ... */ }
```

### `#[WithoutOverlapping]`

`#[WithoutOverlapping]` maps to Laravel's `Event::withoutOverlapping()`. It exposes both parameters:

| Parameter                    | Type   | Default | Purpose                                          |
|------------------------------|--------|---------|--------------------------------------------------|
| `expiresAt`                  | `int`  | `1440`  | Lock expiry in minutes.                          |
| `releaseOnTerminationSignals`| `bool` | `true`  | Release the lock when the process receives a termination signal. |

```php
#[WithoutOverlapping(expiresAt: 60, releaseOnTerminationSignals: false)]
class HeavyJobs
{
    #[Scheduled(Every::Hour)]
    public function import(): void { /* ... */ }
}
```

When `withoutOverlapping: true` is set directly on `#[Scheduled]`, the expiry defaults to 1440 minutes and `releaseOnTerminationSignals` defaults to `true`. Use the `#[WithoutOverlapping]` decorator to control either value.

## Multiple schedules per method

Stack `#[Scheduled]` attributes when you want the same handler to run on different schedules:

```php
class Sync
{
    #[Scheduled(Every::FiveMinutes, name: 'fast-sync')]
    #[Scheduled(Every::Day, name: 'nightly-full-sync')]
    public function run(): void
    {
        // ...
    }
}
```

When you don't supply a name on stacked attributes, generated names get a `#0`, `#1`, ... suffix to avoid collisions:

```php
#[Scheduled(Every::FifteenMinutes)]
#[Scheduled(Every::Hour)]
public function check(): void {}

// Registered as:
//   App\Sync@check#0
//   App\Sync@check#1
```

## Naming

```php
#[Scheduled(Every::Hour, name: 'order-reconciliation')]
```

Names show up in `php artisan schedule:list` and are used by `withoutOverlapping` and `onOneServer` to track running tasks. Pick a stable, descriptive name if those features matter to you.

If you omit the name, the default is `Class@method` (with a `#N` suffix when stacked).

## Dependency injection

Scheduled methods are invoked through the container, so type-hinted dependencies are resolved automatically:

```php
#[Scheduled(Every::Hour)]
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
use NielsJanssen\Laravel\Discovery\Schedule\BetweenTime;
use NielsJanssen\Laravel\Discovery\Schedule\Every;
use NielsJanssen\Laravel\Discovery\Schedule\OnOneServer;
use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;
use NielsJanssen\Laravel\Discovery\Schedule\Timezone;
use NielsJanssen\Laravel\Discovery\Schedule\WithoutOverlapping;

#[Timezone('Europe/Amsterdam')]
#[OnOneServer]
class Cleanup
{
    public function __construct(private Reports $reports) {}

    #[Scheduled(Every::FiveMinutes, name: 'heartbeat')]
    public function heartbeat(): void
    {
        // ping the monitoring service
    }

    #[Scheduled(Every::ThirtyMinutes, between: new BetweenTime('06:00', '23:00'))]
    public function pruneSessions(): void
    {
        // ...
    }

    #[Scheduled(static function (Event $event) {
        $event->dailyAt('03:30')->onSuccess(fn() => Heartbeat::ping('nightly-report'));
    }, name: 'nightly-report')]
    #[WithoutOverlapping(60)]
    public function generateReport(): void
    {
        $this->reports->build();
    }
}
```
