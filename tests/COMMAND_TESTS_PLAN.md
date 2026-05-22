# Command Tests Plan

## Project context

`nielsjanssen/laravel-discovery` is a Laravel package that uses the Tempest Discovery library
(`tempest/discovery`) to automatically register console commands, event listeners, and routes
without manual configuration. This plan covers the command feature only.

---

## How Tempest Discovery works

`BootDiscovery` scans PSR-4 namespaces, passes every class to each `Discovery` implementation
via `discover(DiscoveryLocation $location, ClassReflector $class)`, then calls `apply()` once
scanning is complete. In tests, the easiest approach is to call these two methods **directly**
rather than going through the full boot, the same pattern as in `tests/Feature/EventDiscoveryTest.php`.

The `IsDiscovery` trait provides `$discoveryItems` (a `DiscoveryItems` instance) but it is
**not auto-initialised** — you must call `$discovery->setItems(new DiscoveryItems())` before
calling `discover()`, otherwise PHP throws "uninitialized typed property".

---

## Test infrastructure

| File | Role |
|---|---|
| `tests/TestCase.php` | Extends `Orchestra\Testbench\TestCase`; registers `DiscoveryServiceProvider`; points `discovery.autoload` at `workbench/app` |
| `tests/Pest.php` | `uses(Tests\TestCase::class)->in(__DIR__)` |
| `phpunit.xml` | PHPUnit 12.5 config; test suite = `tests/` |
| `tests/Feature/EventDiscoveryTest.php` | Reference implementation; see `discoverEvents()` helper |

### Pattern used in EventDiscoveryTest (to follow)

```php
function discoverEvents(string ...$classes): Dispatcher
{
    $discovery = new EventDiscovery(app(Dispatcher::class));
    $discovery->setItems(new DiscoveryItems());

    $location = new DiscoveryLocation(
        namespace: 'Tests\\Fixtures\\Event',
        path: dirname(__DIR__) . '/Fixtures/Event',
    );

    foreach ($classes as $class) {
        $discovery->discover($location, new ClassReflector($class));
    }

    $discovery->apply();

    return app(Dispatcher::class);
}
```

Important: `app(Dispatcher::class)` in the event tests already contains listeners from the
workbench boot, so "nothing was registered" assertions must check for the **absence of specific
listener strings** rather than using `toBeEmpty()` — use
`collect($dispatcher->getRawListeners())->flatten()->all()`.

The same pattern applies for the command tests: the kernel may already have registered commands
from the workbench, so avoid `assertEmpty` on the full command list.

---

## Source files under test

| File | Description |
|---|---|
| `src/Feature/Command/CommandDiscovery.php` | Scans classes, populates `CommandDefinition` list |
| `src/Feature/Command/CommandDecorator.php` | Entry point: creates a `DecoratedCommand` from a `CommandDefinition` |
| `src/Feature/Command/DecoratedCommand.php` | The `LaravelCommand` wrapper; runs middleware pipeline |
| `src/Feature/Command/CommandArgumentsDefinition.php` | Maps method parameters to Symfony `InputArgument`/`InputOption` |
| `src/Feature/Command/CommandMiddleware.php` | Interface: `__invoke(Command $command, callable $next): mixed` |
| `src/Feature/Command/ProvidesInputOptions.php` | Optional interface: middleware declares its own input options |
| `src/Feature/Command/ConsoleCommand.php` | Attribute: `name`, `description`, `aliases`, `middleware` |
| `src/Feature/Command/ConsoleArgument.php` | Parameter attribute: override argument name/description |
| `src/Feature/Command/ConsoleOption.php` | Parameter attribute: marks parameter as `InputOption`, with optional aliases |
| `src/Feature/Command/Exception/InvalidCommandRegistrationException.php` | Thrown when class has `#[ConsoleCommand]` but no `__invoke` |
| `src/Feature/Command/Middleware/Transaction.php` | Wraps execution in `DB::transaction()` |
| `src/Feature/Command/Middleware/Caution.php` | Prompts for production confirmation; adds `--force`/`-f` via `ProvidesInputOptions` |

---

## Discovery modes in CommandDiscovery

`CommandDiscovery::discover()` handles four cases:

| Case | What triggers it | Stored as |
|---|---|---|
| Standard Laravel command | Class extends `Illuminate\Console\Command` | `[$classReflector, null]` |
| Invokable class command | `#[ConsoleCommand]` on the class + `__invoke` method | `[$classReflector, $definition]` |
| Method command | `#[ConsoleCommand]` on a public method | `[$methodReflector, $definition]` |
| Invalid | `#[ConsoleCommand]` on the class but **no** `__invoke` | throws `InvalidCommandRegistrationException` |
| Non-instantiable class | Abstract class or interface | skipped (early return) |

`apply()` converts every stored item into a `CommandDefinition($reflector, $definition)` and
appends it to `$this->commands`.

`register()` hooks into `Kernel::class` resolution via `app->afterResolving` and calls
`$kernel->registerCommand(...)` for each command. For commands with a definition, it wraps them
in `CommandDecorator::decorateCommand()`; for plain Laravel commands it resolves them directly.

---

## DecoratedCommand behaviour

`DecoratedCommand::__construct()`:
1. Sets `$this->name` from `ConsoleCommand::name`
2. Calls `parent::__construct()` (Symfony registers the definition)
3. Sets aliases and description
4. Determines reflector type: `ClassReflector` → invokable; `MethodReflector` → method command
5. Builds `CommandArgumentsDefinition` from the `__invoke`/method parameters
6. Adds those arguments/options to the Symfony input definition
7. Iterates `ConsoleCommand::middleware`, resolves each via the container, stores in
   `$this->resolvedMiddleware`; if any implements `ProvidesInputOptions`, its options are also
   added to the input definition

`DecoratedCommand::__invoke()`:
1. Resolves the inner command class from the container (injects `input`/`output`)
2. Builds a pipeline closure that calls the inner method with resolved arguments
3. Wraps it with each middleware (reversed, so outermost middleware runs first), passing `$this`
   (the `DecoratedCommand`) as the `Command` argument
4. Executes the pipeline

---

## Fixtures to create

All fixtures go in `tests/Fixtures/Command/` under namespace `Tests\Fixtures\Command`.

### Command classes

| File | Description |
|---|---|
| `InvokableCommand.php` | `#[ConsoleCommand(name: 'fixture:invokable', description: 'Desc', aliases: ['fixture:inv'])]` on class + `__invoke()`; no parameters |
| `MethodCommand.php` | `#[ConsoleCommand(name: 'fixture:method')]` on a public method |
| `ArgumentCommand.php` | Method with required `string $name` and optional `int $count = 1` parameters |
| `OptionCommand.php` | Method with `#[ConsoleOption] bool $verbose = false` and `#[ConsoleOption(aliases: ['u'])] string $user = ''` parameters |
| `InvalidCommand.php` | `#[ConsoleCommand(name: 'fixture:invalid')]` on the class but no `__invoke` |
| `LaravelStyleCommand.php` | Extends `Illuminate\Console\Command` (plain Laravel, no `#[ConsoleCommand]`) |
| `AbstractCommand.php` | Abstract class with `#[ConsoleCommand]` — should be skipped |
| `MiddlewareCommand.php` | `#[ConsoleCommand(name: 'fixture:middleware', middleware: [RecordingMiddleware::class])]` on a method |

### Middleware fixture

| File | Description |
|---|---|
| `RecordingMiddleware.php` | Implements `CommandMiddleware`; appends to a static `array $calls` with `['command' => $command, 'invoked' => false]`; calls `$next()` and marks `invoked` |
| `OptionMiddleware.php` | Implements `CommandMiddleware` + `ProvidesInputOptions`; adds `--tag` option; reads it and stores in static property |
| `ShortCircuitMiddleware.php` | Implements `CommandMiddleware`; does **not** call `$next()`; returns `42` |

---

## Tests to write

### `tests/Feature/CommandDiscoveryTest.php`

Helper to define (following `discoverEvents` pattern):

```php
function discoverCommands(string ...$classes): CommandDiscovery
{
    $discovery = app(CommandDiscovery::class);
    $discovery->setItems(new DiscoveryItems());

    $location = new DiscoveryLocation(
        namespace: 'Tests\\Fixtures\\Command',
        path: dirname(__DIR__) . '/Fixtures/Command',
    );

    foreach ($classes as $class) {
        $discovery->discover($location, new ClassReflector($class));
    }

    $discovery->apply();

    return $discovery;
}
```

Note: `CommandDiscovery` is `#[Singleton]`, so `app(CommandDiscovery::class)` returns the
same instance for the life of a test's app — safe because Testbench boots a fresh app per test.

Test cases:

1. `it discovers an invokable class with #[ConsoleCommand]`  
   → `discoverCommands(InvokableCommand::class)->commands` has one entry;
   reflector is `ClassReflector`; definition name is `'fixture:invokable'`

2. `it discovers a method annotated with #[ConsoleCommand]`  
   → `discoverCommands(MethodCommand::class)->commands` has one entry;
   reflector is `MethodReflector`

3. `it discovers a class extending LaravelCommand`  
   → `discoverCommands(LaravelStyleCommand::class)->commands` has one entry;
   definition is `null`

4. `it throws for a class with #[ConsoleCommand] but no __invoke`  
   → `discoverCommands(InvalidCommand::class)` throws `InvalidCommandRegistrationException`

5. `it skips abstract classes`  
   → `discoverCommands(AbstractCommand::class)->commands` is empty

---

### `tests/Feature/DecoratedCommandTest.php`

Helper:

```php
use NielsJanssen\Laravel\Discovery\Feature\Command\CommandDecorator;
use NielsJanssen\Laravel\Discovery\Feature\Command\CommandDefinition;
use Tempest\Reflection\ClassReflector;
use Tempest\Reflection\MethodReflector;

function decorateClass(string $class): \Illuminate\Console\Command
{
    $reflector   = new ClassReflector($class);
    $definition  = $reflector->getAttribute(\NielsJanssen\Laravel\Discovery\Feature\Command\ConsoleCommand::class);
    $commandDef  = new CommandDefinition($reflector, $definition);

    return app(CommandDecorator::class)->decorateCommand($commandDef);
}

function decorateMethod(string $class, string $method): \Illuminate\Console\Command
{
    $reflector   = (new ClassReflector($class))->getMethod($method);
    $definition  = $reflector->getAttribute(\NielsJanssen\Laravel\Discovery\Feature\Command\ConsoleCommand::class);
    $commandDef  = new CommandDefinition($reflector, $definition);

    return app(CommandDecorator::class)->decorateCommand($commandDef);
}
```

Test cases:

1. `it sets the command name from the ConsoleCommand attribute`  
   → `decorateClass(InvokableCommand::class)->getName()` equals `'fixture:invokable'`

2. `it sets the command description`  
   → `->getDescription()` equals `'Desc'`

3. `it sets command aliases`  
   → `->getAliases()` contains `'fixture:inv'`

4. `it adds method parameters as input arguments`  
   → `decorateMethod(ArgumentCommand::class, 'run')` has `name` (required) and `count` (optional)
   arguments in its `getDefinition()`

5. `it adds ConsoleOption parameters as input options`  
   → option `verbose` is `VALUE_NONE`; option `user` has shortcut `u`

6. `it adds middleware-provided options to the input definition`  
   → `decorateMethod(MiddlewareCommand::class, 'run')` (where middleware is `OptionMiddleware`)
   has `tag` in its option definition

7. `it passes the DecoratedCommand instance (not the inner class) to middleware`  
   → run the pipeline; `RecordingMiddleware::$calls[0]['command']` is an instance of
   `\Illuminate\Console\Command`

8. `it calls middleware in the correct order (outermost first)`  
   → with two middlewares, outer wraps inner; verify order via `RecordingMiddleware::$calls`

9. `it short-circuits when middleware does not call $next`  
   → with `ShortCircuitMiddleware`, the inner command is never executed and the return value is `42`

---

### `tests/Feature/CommandArgumentsDefinitionTest.php`

Directly instantiate `CommandArgumentsDefinition::from(MethodReflector $method)` using
`ClassReflector::getMethod()` on fixture classes.

Test cases:

1. `it creates a required InputArgument for a typed parameter with no default`

2. `it creates an optional InputArgument for a parameter with a default value`

3. `it creates an IS_ARRAY InputArgument for an array-typed parameter`

4. `it creates a VALUE_NONE InputOption for a bool parameter with #[ConsoleOption]`

5. `it creates an InputOption with a shortcut from ConsoleOption::aliases`

6. `it uses the name from #[ConsoleArgument] over the parameter name`

7. `it uses the name from #[ConsoleOption] over the parameter name`

8. `it resolves input values to an array matching the method signature order`  
   → create an `ArrayInput` with known values; assert `resolveInput()` returns them in order

---

### Integration test (optional, in `DecoratedCommandTest.php` or its own file)

Using `$this->artisan()` from `pestphp/pest-plugin-laravel` (already in `require-dev`):

```php
it('executes a discovered command end-to-end', function () {
    // The workbench boot already discovers app:invokable via CommandDiscovery
    $this->artisan('app:invokable')->assertSuccessful();
});
```

This requires `defineEnvironment` in the test to point `discovery.autoload` at `workbench/app`
(already the default in `TestCase`).

---

## Key gotchas

- **`CommandDiscovery` is `#[Singleton]`** — `app(CommandDiscovery::class)` returns one instance
  per app boot. Always call `setItems(new DiscoveryItems())` to reset state.
- **Middleware is pre-resolved in the constructor** — `DecoratedCommand` resolves middleware
  instances at construction time (not at invocation). Test middleware must be resolvable from the
  container (either auto-wired or with no constructor dependencies).
- **Static middleware state** — `RecordingMiddleware::$calls` is static; reset it in each test
  with `RecordingMiddleware::$calls = []`.
- **`$this->artisan()` requires the command to be registered with the kernel** — this only
  happens via `CommandDiscovery::register()`'s `afterResolving(Kernel::class, ...)` hook, which
  fires when the kernel is first resolved. Testbench resolves the kernel during test setup, so
  commands discovered during the workbench boot will be available; commands discovered manually
  in a test via `discoverCommands()` will **not** be registered with the kernel unless you
  explicitly call `$kernel->registerCommand(...)`.
- **Input definition is frozen after Symfony parses input** — `ProvidesInputOptions::getOptions()`
  is called in `DecoratedCommand::__construct()`, before Symfony parses arguments. This is the
  correct place; do not try to add options during `__invoke()`.
