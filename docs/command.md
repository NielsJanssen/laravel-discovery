# Commands

Discoverable Artisan commands come in two forms:

1. **Attribute-driven**. Annotate any public method with `#[ConsoleCommand]`. No base class required, method parameters
   become Artisan arguments and options.
2. **Classic**. A class extending `Illuminate\Console\Command`. Drop the file into your `app/` namespace and discovery
   registers it. No `console.php` listing needed.

## Attribute-driven commands

```php
use NielsJanssen\Laravel\Discovery\Command\ConsoleCommand;

class Maintenance
{
    #[ConsoleCommand('maintenance:warmup', 'Warm up application caches')]
    public function warmup(): void
    {
        // ...
    }
}
```

Run it:

```bash
php artisan maintenance:warmup
```

You can group as many commands as you like into a single class. Discovery looks at every public method that carries the
attribute.

### The `#[ConsoleCommand]` attribute

```php
#[ConsoleCommand(
    name: 'cache:hot-keys',
    description: 'Print the hottest cache keys',
    aliases: ['cache:popular', 'cache:top'],
    middleware: [Benchmark::class],
)]
```

| Parameter     | Type                           | Purpose                                                                                                     |
|---------------|--------------------------------|-------------------------------------------------------------------------------------------------------------|
| `name`        | `string`                       | The Artisan command name. **Required.**                                                                     |
| `description` | `?string`                      | Description shown in `php artisan list`.                                                                    |
| `aliases`     | `list<string>`                 | Alternative names the command also responds to.                                                             |
| `middleware`  | `list<class-string\|callable>` | Middleware classes or callables, executed in order around the handler. See [Middleware](#middleware) below. |

## Arguments and options

Method parameters become Artisan input automatically.

```php
use NielsJanssen\Laravel\Discovery\Command\ConsoleArgument;
use NielsJanssen\Laravel\Discovery\Command\ConsoleOption;

#[ConsoleCommand('users:import', 'Import users from a CSV')]
public function import(
    string $path,
    #[ConsoleArgument(description: 'Batch number')] int $batch = 100,
    #[ConsoleOption(shortcut: 'd')] bool $dryRun = false,
    #[ConsoleOption(name: 'tag', shortcut: 't')] array $tag = [],
): void {
    // ...
}
```

Rules of thumb:

- A typed parameter with **no attribute** is treated as a `ConsoleArgument`.
- Add `#[ConsoleArgument]` if you want a custom name or description.
- Add `#[ConsoleOption]` if it should be an option (`--foo`) instead of a positional argument.
- A `bool` option is a flag (`VALUE_NONE`); present means `true`.
- A parameter with a default value becomes optional; without one, it's required.
- Names are automatically converted to kebab-case (`$dryRun` becomes `--dry-run`).
- Parameter types must be **scalar** (`string`, `int`, `float`, `bool`) or `array`. Other types are ignored and won't
  appear as input.

### `#[ConsoleArgument]`

```php
#[ConsoleArgument(name: 'output-dir', description: 'Where to write files')] string $outputDir
```

| Parameter     | Default | Purpose                                                                   |
|---------------|---------|---------------------------------------------------------------------------|
| `name`        | `null`  | Override the argument name (defaults to the parameter name, kebab-cased). |
| `description` | `null`  | Help text shown for `--help`.                                             |

`array`-typed parameters automatically become variadic Symfony `IS_ARRAY` arguments.

### `#[ConsoleOption]`

```php
#[ConsoleOption(name: 'force', shortcut: 'f', description: 'Skip safety checks')] bool $force = false
```

| Parameter     | Default | Purpose                                                                 |
|---------------|---------|-------------------------------------------------------------------------|
| `name`        | `null`  | Override the option name (defaults to the parameter name, kebab-cased). |
| `shortcut`    | `null`  | Short flag (no dashes). `'f'` becomes `-f`.                             |
| `description` | `null`  | Help text shown for `--help`.                                           |

## Injecting dependencies and IO

Method parameters that don't map to scalar input are still resolved by Laravel's container. You can mix CLI input and DI
freely:

```php
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\Input;

use function Laravel\Prompts\note;

#[ConsoleCommand('reports:rebuild', 'Rebuild reports')]
public function rebuild(
    string $month,          // CLI arg
    OutputStyle $output,    // Optionally available for writing to the output
    Input $input,           // Optionally available for reading input
    ReportBuilder $builder, // resolved from the container
): void {
    // ...
    note('Report generated!');
}
```

Both `input` and `output` can be added as parameters and will be populated with the standard command IO. Or you can use
Laravel Prompts.

## Middleware

Middleware lets you wrap commands with cross-cutting behavior. It runs onion-style: the first item starts first and
finishes last.

```php
#[ConsoleCommand(
    name: 'db:rebuild',
    description: 'Drop and rebuild the database',
    middleware: [Benchmark::class, Transaction::class, Caution::class],
)]
```

A middleware implements `CommandMiddleware`:

```php
namespace NielsJanssen\Laravel\Discovery\Command;

interface CommandMiddleware
{
    public function __invoke(\Illuminate\Console\Command $command, callable $next): mixed;
}
```

Call `$next()` to continue down the chain; return its result (or substitute your own).

### Built-in middleware

#### `Benchmark`

Prints the total wall-time of the command:

```text
Command finished in 412.30ms
```

Use it on commands where execution time matters: migrations, imports, anything you might tune later.

```php
#[ConsoleCommand('reports:rebuild', middleware: [Benchmark::class])]
```

#### `Transaction`

Wraps the command body in `DB::transaction()`. If anything throws, the entire database mutation is rolled back.

```php
#[ConsoleCommand('users:import', middleware: [Transaction::class])]
```

#### `Caution`

Adds a `--force / -f` flag automatically, and on production environments shows a "do you really want to do this?"
confirmation. Returns exit code `1` if the user says no.

```php
#[ConsoleCommand('users:purge', middleware: [Caution::class])]
```

Skip the prompt with `--force` (handy in CI):

```bash
php artisan users:purge --force
```

### Providing extra options from middleware

Need your middleware to inject its own CLI options (like `Caution` does with `--force`)? Implement
`ProvidesInputOptions` in addition to `CommandMiddleware`:

```php
use NielsJanssen\Laravel\Discovery\Command\CommandMiddleware;
use NielsJanssen\Laravel\Discovery\Command\ProvidesInputOptions;
use Symfony\Component\Console\Input\InputOption;

class Audit implements CommandMiddleware, ProvidesInputOptions
{
    public function __construct(private LoggerInterface $logger) {}

    public function getOptions(): array
    {
        return [
            new InputOption('actor', null, InputOption::VALUE_REQUIRED, 'Who ran this command'),
        ];
    }

    public function __invoke($command, callable $next): mixed
    {
        $actor = $command->option('actor') ?? 'system';
        $this->logger->info('command executed', ['by' => $actor]);

        return $next();
    }
}
```

The option is added to the command's definition automatically when the middleware is registered.

### Resolution

Middleware listed by class name (`Benchmark::class`) is resolved through Laravel's container, so constructor injection
works. You can also pass an already-resolved instance or an invokable callable; both are used as-is.

## Classic Laravel commands

Anything that extends `Illuminate\Console\Command` and lives in a discoverable namespace is registered automatically. No
changes to your existing commands needed.

```php
namespace App\Console\Commands;

use Illuminate\Console\Command;

class GreetCommand extends Command
{
    protected $signature = 'greet {name}';
    protected $description = 'Say hello';

    public function handle(): void
    {
        $this->info("Hello, {$this->argument('name')}!");
    }
}
```

## Troubleshooting

**Command not showing up in `php artisan list`?**

1. Confirm the file lives under a namespace declared in your project's `composer.json` `autoload.psr-4`. Discovery only
   scans namespaces it knows about.
2. Confirm the file isn't covered by a path in `skip_paths` (empty by default).
3. In production, run `php artisan discovery:clear` and then `discovery:cache` again.
4. For `#[ConsoleCommand]`, confirm the containing class is **instantiable**. Abstract classes and traits are ignored.

**Symfony complains "Option already exists"?**

This happens when stacked middleware add the same option (for example, two middlewares both adding `--force`). Rename
one, or share a single middleware.
