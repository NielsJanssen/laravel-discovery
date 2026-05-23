# Events

Discoverable event listeners. Decorate a method with `#[EventHandler]` and the package wires it into Laravel's event
dispatcher.

```php
use NielsJanssen\Laravel\Discovery\Event\EventHandler;

class Notifications
{
    #[EventHandler]
    public function onUserRegistered(UserRegistered $event): void
    {
        // ...
    }
}
```

The event class is inferred from the first parameter's type. No explicit registration. No `EventServiceProvider`
bookkeeping.

## The `#[EventHandler]` attribute

```php
#[EventHandler(event: SomeEvent::class, deferred: true)]
```

| Parameter  | Default | Purpose                                                                                                |
|------------|---------|--------------------------------------------------------------------------------------------------------|
| `event`    | `null`  | Explicit event class. Use this when you can't infer from the parameter type (or want to override it).  |
| `deferred` | `false` | If `true`, defers listener registration until the listener class is first resolved from the container. |

## How event inference works

The package reads the type of the listener's **first parameter**:

```php
#[EventHandler]
public function onPaid(OrderPaid $event): void { /* listens to OrderPaid */ }
```

It also handles union types. The listener is registered once per class in the union:

```php
#[EventHandler]
public function onUserEvent(UserCreated|UserUpdated|UserDeleted $event): void
{
    // Registered for all three events.
}
```

If a method has no parameters, or the first parameter isn't a class/interface, it's skipped; there's no way to know
what to listen for.

If you'd rather be explicit:

```php
#[EventHandler(event: OrderPaid::class)]
public function onPaid($event): void { /* ... */ }
```

## Multiple listeners in one class

There's no limit. Each method gets its own attribute:

```php
class UserListener
{
    #[EventHandler]
    public function welcome(UserRegistered $event): void { /* ... */ }

    #[EventHandler]
    public function notifyAdmins(UserRegistered $event): void { /* ... */ }

    #[EventHandler]
    public function trackChange(UserUpdated $event): void { /* ... */ }
}
```

Both `welcome` and `notifyAdmins` will fire when `UserRegistered` is dispatched.

## Deferred listeners

By default, listeners are registered eagerly when the service provider boots. If you are in control of the lifecycle of
a listener class, you can defer registration until the first time it's resolved from the container. This is especially
useful for listeners that should be registered only in certain contexts. For example a listener that only runs for a
certain command:

```php
class WorkCommand
{
    #[ConsoleCommand('app:work')]
    public function __invoke() {
        // ...
    }

    #[EventHandler(deferred: true)]
    public function process(CommandFinished $event): void
    {
        $event->output->writeln('app:work command finished!');
    }
}
```

The listener is not registered with the dispatcher until the first time the `WorkCommand` is resolved from the
container, which happens when the command is executed.

Use this with caution. Deferred listeners won't run if the class is never resolved, so always pair it with something you
know will run, like a command, request or schedule.

## A larger example

```php
namespace App\Listeners;

use App\Events\OrderPaid;
use App\Events\OrderRefunded;
use App\Events\PaymentFailed;
use App\Services\Receipts;
use App\Services\Notifier;
use NielsJanssen\Laravel\Discovery\Event\EventHandler;

class OrderListener
{
    public function __construct(
        private Receipts $receipts,
        private Notifier $notifier,
    ) {}

    #[EventHandler]
    public function emailReceipt(OrderPaid $event): void
    {
        $this->receipts->send($event->order);
    }

    #[EventHandler]
    public function notifyOnIncident(PaymentFailed|OrderRefunded $event): void
    {
        $this->notifier->ping($event);
    }
}
```
