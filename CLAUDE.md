# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is the **laravel-discovery** mono-repo, containing two packages that bring attribute-based discovery to Laravel
applications using [Tempest Discovery](https://tempestphp.com/):

- **`nielsjanssen/laravel-discovery`** (`packages/laravel-discovery/`) — Core: discovers Commands, Events, Routes, and
  Schedules
- **`nielsjanssen/laravel-discovery-graphql`** (`packages/laravel-discovery-graphql/`) — Rebing GraphQL integration:
  discovers GraphQL types, queries, and mutations (including method-level `#[Query]`/`#[Mutation]` actions)

The root `composer.json` is a `type: project` aggregator that `replace`s both packages with `self.version`, so
installing the root pulls in everything. Each sub-package has its own `composer.json` so it can be split out to its own
repository on release (see `.github/workflows/release.yml` and the SPLIT token).

## Development Workflow

### Common Commands

```bash
composer install          # Install dependencies (auto-runs clear + prepare)
composer test             # Run all tests (Pest)
composer lint             # Lint code (Laravel Pint)
composer build            # Build the workbench test application
composer serve            # Serve workbench locally
```

```bash
vendor/bin/pest tests/Feature/Command/CommandDiscoveryTest.php           # Single file
vendor/bin/pest tests/Feature/Command/CommandDiscoveryTest.php --filter=name  # Single test
vendor/bin/pest --ci      # CI mode (parallel)
```

The `post-autoload-dump` hook runs `testbench package:purge-skeleton` then `testbench package:discover`. If discovery
seems stale after editing classes, re-run `composer dump-autoload`.

## Code Architecture

### Repository Layout

```
packages/
  laravel-discovery/           # Core package
    src/                       # NielsJanssen\Laravel\Discovery\
      Command/                 # #[ConsoleCommand], middleware
      Event/                   # #[EventHandler]
      Router/                  # #[Get], #[Post], etc.
      Schedule/                # #[Scheduled], Every, Cron, ScheduleDecorator
      Laravel/                 # Artisan commands (discovery:cache, make:discovery)
      DiscoveryServiceProvider.php
    config/discovery.php
    stubs/                     # make:discovery stub
  laravel-discovery-graphql/   # GraphQL package
    src/RebingGraphQL/         # NielsJanssen\Laravel\Discovery\RebingGraphQL\
tests/
  Feature/                     # One folder per discovery system
  Fixtures/                    # Fixture classes per discovery system
  TestCase.php
workbench/                     # Minimal Laravel app for integration tests
```

PSR-4 autoload from the root `composer.json`:

- `NielsJanssen\Laravel\Discovery\` → `packages/laravel-discovery/src/`
- `NielsJanssen\Laravel\Discovery\RebingGraphQL\` → `packages/laravel-discovery-graphql/src/RebingGraphQL/`

When referencing source files, use the full `packages/<pkg>/src/...` path — there is no top-level `/src` directory.

### Discovery Systems

All discoverers implement Tempest's `Discovery` interface, use the `IsDiscovery` trait, and are themselves discovered by
`DiscoveryDiscovery` when `BootDiscovery` scans the configured locations. They follow a two-phase contract:

1. `discover(DiscoveryLocation, ClassReflector)` — called per class; populates `$this->discoveryItems` with serializable
   DTOs (so they can be cached)
2. `apply()` — called once after all discovery is complete; registers items with Laravel (`Artisan::starting()`,
   `Event::listen()`, `Route::*`, `Schedule::call()`, `graphql.schemas` config)

### Discovery Flow

1. `DiscoveryServiceProvider::register()` binds `DiscoveryConfig` (using `discovery.autoload` from config) and
   `DiscoveryCache` (FULL strategy in `discovery.cache_environments`, NONE otherwise). The pool comes from
   `discovery.cache_store`: `files` is a Symfony `PhpFilesAdapter` written only by `discovery:cache`, `memory` is
   `Cache\MemoryAdapter`, an `ArrayAdapter` subclass that holds the process-lifetime instance and its own warm flag, and
   `boot()` fills it once per process (`MemoryAdapter::forget()`, resets it)
2. `DiscoveryServiceProvider::boot()` calls `BootDiscovery`, then stores resolved discovery class names in
   `config('discovery.discovery_classes')`
3. `BootDiscovery` runs `DiscoveryDiscovery` first to find every `Discovery` implementation
4. Each discoverer's `discover()` is invoked for every class in every `DiscoveryLocation`; `apply()` runs after

### How Discovery Finds the Package's Own Classes

`DiscoveryConfig::autoload(base_path())` reads the host project's `composer.json` and scans:

- **Vendor packages** that require any `tempest/*` package (the laravel-discovery packages require `tempest/discovery`,
  so they are auto-scanned in consumer apps)
- **App namespaces** defined in the root `composer.json`

For the test environment, the package is the root project and the root `composer.json` lists the package namespaces
directly under `autoload.psr-4`, so `DiscoveryConfig::autoload(base_path())` (the default) scans them. The current
`TestCase::defineEnvironment()` is empty — no manual `discovery.autoload` override is needed.

### Configuration

`packages/laravel-discovery/config/discovery.php`:

```php
return [
    'autoload' => base_path(),              // Directory with composer.json
    'skip_classes' => [],
    'skip_paths' => [],
    'cache_path' => 'framework/cache/discovery',
    'cache_environments' => ['production'],   // DISCOVERY_CACHE_ENVIRONMENTS, comma separated
    'cache_store' => 'files',                 // DISCOVERY_CACHE_STORE: files|memory
];
```

### Command Discovery

`packages/laravel-discovery/src/Command/`

- **`#[ConsoleCommand]`** marks a method as an Artisan command
- **`Command`** class wraps discovered methods, resolves typed parameters as args/options, runs middleware
- **`CommandArgumentsDefinition`** parses parameters into Symfony input definitions; honors `#[ConsoleArgument]` and
  `#[ConsoleOption]`
- **Middleware**: `Benchmark`, `Transaction`, `Caution`; implement `CommandMiddleware`; middleware that adds CLI options
  implements `ProvidesInputOptions`
- Plain Laravel commands (extending `Illuminate\Console\Command`) are also auto-registered

```php
class MyCommands
{
    #[ConsoleCommand('migrate:custom', 'Run migrations', middleware: [Benchmark::class])]
    public function migrate(
        #[ConsoleArgument] string $path,
        #[ConsoleOption(shortcut: 'f')] bool $fresh = false,
    ): void {}
}
```

### Event Discovery

`packages/laravel-discovery/src/Event/`

- **`#[EventHandler]`** marks a method as an event listener; event class is inferred from the first parameter's type if
  not explicit
- Union-typed parameters register the listener for every class in the union
- `deferred: true` defers handler-class resolution until dispatch time

### Route Discovery

`packages/laravel-discovery/src/Router/`

- HTTP-method attributes: `Get`, `Post`, `Put`, `Patch`, `Delete`, `Head`, `Options` (all extend `Route`/implement
  `Routable`)
- Decorators implementing `RouteDecorator`: `Prefix`, `Middleware`, `Domain` — applied class-level then method-level
- Method attributes are repeatable, so one method can serve multiple URIs

### Schedule Discovery

`packages/laravel-discovery/src/Schedule/`

- **`#[Scheduled]`** is `IS_REPEATABLE`; its first argument is `Cron|Every|\Closure(Event): void`
- **`Every`** enum (NOT `Frequency`) — granular cases like `Every::Second`, `Every::FiveSeconds`, `Every::Minute`,
  `Every::FiveMinutes`, `Every::Hour`, `Every::Day`, `Every::Week`, `Every::Month`, `Every::Quarter`, `Every::Year`.
  Each maps to an `Interval` and then to a cron expression; sub-minute cases also call `$event->repeatEvery($seconds)`
- **`Cron`** value object: `new Cron('*/5 * * * *')` — pass any cron expression verbatim
- **Per-schedule options** on `#[Scheduled]`: `between: new BetweenTime('09:00', '17:00')`, `unlessBetween`, `name`,
  `withoutOverlapping`, `onOneServer`, `timezone`
- **Decorators** (implement `ScheduleDecorator`, apply class-level or method-level to set defaults on every
  `#[Scheduled]` in scope): `BetweenTime`, `OnOneServer`, `Timezone`, `WithoutOverlapping`
- `DiscoveredSchedule` stores `className`/`methodName`/`attributeIndex` so closure-based schedules survive caching:
  closures are stripped in `discover()` and re-read via reflection in `apply()`

```php
#[Timezone('Europe/Amsterdam')]
class Tasks
{
    #[Scheduled(Every::FiveMinutes)]
    public function syncData(): void {}

    #[Scheduled(new Cron('0 9 * * 1-5'), withoutOverlapping: true)]
    public function workdayReport(): void {}

    #[Scheduled(static function (Event $event) {
        $event->hourly()->withoutOverlapping()->onOneServer();
    })]
    #[Scheduled(Every::FiveMinutes, name: 'quick-sync')]
    public function flexibleTask(): void {}
}
```

Note: `apply()` is a no-op when not running in console (`$this->app->runningInConsole()` guard), so web requests don't
pay for schedule registration.

### GraphQL Discovery (Rebing)

`packages/laravel-discovery-graphql/src/RebingGraphQL/`

Two registration modes coexist:

1. **Class-based** — classes extending Rebing's `Type`, `Query`, or `Mutation` are auto-registered in
   `config('graphql.schemas')` (default schema). `QueryField`/`MutationField` are excluded since they are the dynamic
   wrappers for mode 2.
2. **Action-based** (preferred for new code) — methods annotated with `#[Query]` or `#[Mutation]` are registered as
   discovered `Field` instances (`QueryField`/`MutationField` via the `AsActionField` trait):
    - Return type is inferred from the method's PHP return type hint when scalar (or `void` → `NullType`); otherwise
      `type:` must be specified on the attribute
    - A nullable PHP return type sets `nullable` on the action, **including** when `type:` is given explicitly (`type:`
      says which type, not whether the field may be null). Inference only widens: `nullable: true` survives a
      non-nullable return, and an undeclared return type leaves the field non-null
    - `#[Query]` / `#[Mutation]` accept `description:` (surfaced as the field description in GraphiQL)
    - Each parameter becomes a GraphQL arg **unless** it is one of the injection sources below
    - `#[Arg]` accepts `name`, `description`, `rules` (array or `Closure` for lazy validation), and `deprecationReason`
    - The discovered action is bound as a singleton at `discovery.rebing_graphql.<sha256-of-item>`; the schema config is
      only written when `configurationIsCached()` is false (so cached config wins in production)

**Parameter injections.** A `#[Query]`/`#[Mutation]` method can mix GraphQL args with values plucked from the resolver
call. These parameters are *excluded* from the generated `args()` definition:

- `#[Root] mixed $root` — the parent object (top-level queries see `null`)
- `#[Context] mixed $context` — Rebing's context value (typically built by `AddAuthUserContextValueMiddleware` or a
  custom context factory)
- `GraphQL\Type\Definition\ResolveInfo $info` — detected by **type**, no attribute needed; the standard webonyx
  resolve-info handle

Internally these become entries in `DiscoveredAction::$injections` keyed by `paramName`, and `AsActionField::resolve()`
fills them in alongside the real GraphQL args before calling the user's method.

**Model binding.** A parameter typed as an Eloquent `Model` is **not** treated as a GraphQL arg or a container
injection — instead the schema exposes a scalar **`ID` arg** (named via `#[Arg]` or the param name) and
`AsActionField::resolve()` fetches the model from that value (`where(getRouteKeyName(), $value)`) and injects the
instance into the method (so resolvers can run policy checks against a real model). The trigger is the model *type
alone* — no attribute required; `#[Arg]` only renames the arg, adds rules, or overrides the type.

- Lookup is always by the model's **route key** (`getRouteKeyName()`).
- Non-nullable bindings auto-add a `Rule::exists(table, routeKey)` validation rule; nullable bindings (`?User $user` / a
  default) add no `exists` rule and resolve to `null` for a missing/absent value. Any `#[Arg(rules:)]` are merged with
  the auto `exists` rule.
- Default GraphQL arg type is `ID`; override with `#[Arg(type: '...')]`. The resolver's *return* type is unaffected — a
  `: User` return still needs `#[Query(type: 'User')]` (or a registered GraphQL type).
- Carried on `DiscoveredAction::$modelBindings` (a list of serialize-safe `DiscoveredModelBinding` DTOs, so discovery
  caching is unaffected). Note `#[CurrentUser]`/`#[Root]`/`#[Context]` on a model param still win, since those are
  classified first.

```php
#[Query(type: 'User')]
public function user(#[Arg('id')] User $user): User
{
    Gate::authorize('view', $user);   // $user is the resolved Eloquent model
    return $user;
}
```

**Deprecation.** Use PHP 8.4's native `#[\Deprecated(message:, since:)]` attribute on the method (the field), or
`#[Arg(deprecationReason: '...')]` on a parameter — PHP's native `Deprecated` cannot target parameters, hence the `Arg`
-level field. Both surface as GraphQL's `deprecationReason`. Method-level `message` + `since` are concatenated as
`"{message} (since {since})"`.

**Decorators (`ActionDecorator` interface).** Class-level and method-level attributes implementing `ActionDecorator` are
collected generically by `GraphQLDiscovery::discover()` and applied to each `Action` via `decorate()` — method-level
first, then class-level, so method-level wins for decorators that use first-wins semantics
(`if ($action->X === null) ...`). Adding a new decorator only needs the attribute class + `implements ActionDecorator`;
no edit to the discovery flow.

- **`#[Schema('name')]`** (`TARGET_CLASS | TARGET_METHOD`) routes a `#[Query]`/`#[Mutation]` action to a named GraphQL
  schema instead of `default`. Priority: explicit `#[Query(schema: '…')]` arg > method-level `#[Schema]` > class-level
  `#[Schema]`. Only applies to action methods — class-based registrations (classes extending Rebing's `Type`/`Query`/
  `Mutation`) currently always land in `default` since there is no `Action` to decorate.

**Middleware (`#[Middleware]`).** Repeatable, `TARGET_CLASS | TARGET_METHOD`. Carries one or more
`class-string<\Rebing\GraphQL\Support\Middleware>` values. Class-level attributes are flattened first, then method-level
(so class middleware wraps method middleware — class is outermost). The list is exposed to Rebing through an
`AsActionField::getMiddleware()` override, so it flows through Rebing's existing
`Pipeline::send($arguments)->through($middleware)->via('resolve')` pipeline alongside global middleware and
`terminate()` hooks. We do **not** ship a custom middleware interface — users extend `Rebing\GraphQL\Support\Middleware`
and inherit its `handle(mixed $root, array $args, mixed $context, ResolveInfo $info, Closure $next): mixed` contract.

**Authorization (`#[Authorize]`).** Repeatable, `TARGET_CLASS | TARGET_METHOD | TARGET_PARAMETER`. Three modes:

- **Bare `#[Authorize]`** — enforces "must be logged in" via `auth()->check()`. Works regardless of whether Rebing's
  `AddAuthUserContextValueMiddleware` is enabled.
- **`#[Authorize(gate: SomeGate::class)]`** — delegates to a class implementing
  `AuthorizationGate::check(mixed $root, array $args, mixed $context, ?ResolveInfo $info): bool`. The gate is resolved
  from the container, so it gets constructor DI. Use this for anything more involved than a logged-in check (role
  checks, context-dependent rules, etc).
- **`#[Authorize('ability')]` on a model-bound parameter** — runs `Gate::allows($ability, $model)` against the record
  that parameter binds. This is how a bound model gets authorized; see below.

```php
#[Query('aantekening', type: 'Aantekening')]
public function get(#[Arg('id')] #[Authorize('view')] Aantekening $note): ?Aantekening
{
    return $note;   // AantekeningPolicy::view($user, $note) has already passed
}
```

The check runs inside `Field::authorize()`, i.e. **before** validation, preserving Rebing's authorize-first ordering so
an unauthorized caller cannot probe validation rules to map the API. The cost is that the model is loaded once in
`authorize()` and again in `resolve()`; there is no shared per-request binding store yet.

Nullability decides what a **missing record** means:

| binding                   | record not found                    | rationale                                                                                               |
|---------------------------|-------------------------------------|---------------------------------------------------------------------------------------------------------|
| `Model $m` (non-nullable) | denied, same error as "not allowed" | a refusal never confirms whether the record exists                                                      |
| `?Model $m = null`        | check skipped, resolver gets `null` | the signature already says absence is a valid outcome, so the field returns `null` rather than an error |

A found record is always held to its ability, nullable or not.

A failed parameter-level check reports **`Forbidden`** (`DiscoveredModelAuthorization::DEFAULT_MESSAGE`) rather than
Rebing's `Unauthorized`, since the caller is authenticated but not allowed; `message:` overrides it. The class/method
forms keep Rebing's default.

Discovery rejects the shapes that can't work, rather than letting them silently pass: `#[Authorize]` with no ability on
a parameter, `#[Authorize(gate:)]` on a parameter (a gate only sees raw args), `#[Authorize('ability')]` on a parameter
that binds no model, and **laravel-validation's `#[Can]` on a model-bound parameter** — that one validates the raw `ID`
arg, so the gate receives an id string where the policy expects a record; it is a `LogicException` at discovery pointing
at `#[Authorize]`.

Attributes are collected class-first then method-first; **all** must pass (AND semantics). The first failing attribute's
optional `message:` surfaces via `getAuthorizationMessage()` (otherwise Rebing's default "Unauthorized"). Hooks in via
overrides of `Field::authorize()` and `Field::getAuthorizationMessage()` on `AsActionField`, so Rebing's resolver
pipeline blocks the request **before** validation runs.

**In-resolver checks (`Authorization`).** Type-hint `Authorization` on an action to authorize from inside the method,
for checks the attribute cannot express — a subject computed in the body, a branch that only guards part of the work, a
second ability after the first has passed:

```php
#[Query(type: 'Report')]
public function report(#[Arg('id')] Dossier $dossier, Authorization $auth): Report
{
    $auth->authorize('view', $dossier);
    $auth->authorize(ReportAbility::Export, message: 'Not your report');

    return $dossier->report();
}
```

`authorize(string|iterable|\UnitEnum $abilities, mixed $arguments = [], ?string $message = null)` delegates to
`Gate::denies()` — so a list requires **every** ability, and enums resolve through Laravel's `enum_value()`. On failure
it throws Rebing's `AuthorizationError`, which is client-safe and carries the `authorization` category, defaulting to
the same `Forbidden` as the attribute. Laravel's own `Gate::authorize()` is the wrong tool here: its
`AuthorizationException` is not client-safe and surfaces as *Internal server error*.

Injection takes one of two routes, both giving the same stateless object: `#[Authorize]` on the class or method provides
it as a value object (`ActionArgProvider::provideValueObjects()` → `ComposedFromArgs::fromArgs()`), and without that
attribute it resolves straight from the container. Either way it is not a GraphQL arg.

Prefer `#[Authorize('ability')]` on the parameter when it fits — it runs before validation, where this helper runs
inside the resolver, after it.

```php
#[Schema('admin')]
class AdminQueries
{
    #[Query(type: 'User', list: true)]
    public function users(): array { /* lands in admin schema */ }

    #[Query]
    #[Schema('reports')]               // method-level overrides class-level
    public function reportSummary(): string { /* lands in reports schema */ }

    #[Query(schema: 'public')]         // explicit arg overrides both decorators
    public function ping(): string { return 'pong'; }
}
```

```php
class BookQueries
{
    #[Query(type: 'Book', list: true)]
    public function books(
        #[Arg(rules: ['nullable', 'string'])] ?string $title = null,
    ): array { /* ... */ }

    #[Query] // return-type inferred as `string`, non-null
    public function greet(string $name): string
    {
        return "Hello, {$name}!";
    }

    #[Mutation] // void → Null scalar, nullable
    public function clearCache(): void {}
}
```

`GraphQLDiscovery` short-circuits cleanly if `Rebing\GraphQL\GraphQL` isn't loaded, so the package is safe to install
even without the GraphQL package present (though the composer dependency makes that unlikely in practice).

## Testing Architecture

**Framework**: Pest 4 (with PHPUnit base), Orchestra Testbench 11, configured via `testbench.yaml` and `phpunit.xml`.

**Workbench** (`workbench/`): a real Laravel app used for integration tests. `WorkbenchServiceProvider` and
`GraphQLServiceProvider` are registered alongside `DiscoveryServiceProvider` in `tests/TestCase.php`.

**Test helpers** (defined per-feature file): `discoverCommands()`, `discoverRoutes()`, `registerRoutes()`,
`discoverSchedule()`, `discoverGraphQL()` — each builds a fresh discoverer, runs `discover()` over specific fixture
classes, then calls `apply()`. This avoids cross-test pollution from singletons.

**Singleton isolation**: Discoverers marked `#[Singleton]` (e.g. `ScheduleDiscovery`) are resolved once during boot.
Feature tests that exercise such a discoverer in isolation must reset both the discoverer and any Laravel singleton it
wraps in `beforeEach`:

```php
beforeEach(function () {
    app()->forgetInstance(Schedule::class);
    app()->forgetInstance(ScheduleDiscovery::class);
});
```

**Writing tests**: New code paths must be covered by tests before a task is considered complete. The fixtures pattern
(one folder per discovery system under `tests/Fixtures/`) is the convention — add narrow fixture classes that exercise
the specific code path rather than reusing existing ones.

## Built-in Artisan Commands

- `php artisan discovery:cache` — generate discovery cache (also invoked by `php artisan optimize`)
- `php artisan discovery:clear` — clear discovery cache (also invoked by `php artisan optimize:clear`)
- `php artisan make:discovery` — scaffold a new custom `Discovery` class from `stubs/`

## Key Files

| File                                                                        | Purpose                                                                        |
|-----------------------------------------------------------------------------|--------------------------------------------------------------------------------|
| `packages/laravel-discovery/src/DiscoveryServiceProvider.php`               | Registers `DiscoveryConfig`/`DiscoveryCache` singletons; wires `optimize`      |
| `packages/laravel-discovery/src/Cache/MemoryAdapter.php`                    | Process-lifetime discovery cache, so a test run scans once                     |
| `packages/laravel-discovery/src/Command/CommandDiscovery.php`               | Discovers `#[ConsoleCommand]`; registers via `Artisan::starting()`             |
| `packages/laravel-discovery/src/Command/Command.php`                        | Method-as-command wrapper; runs middleware pipeline                            |
| `packages/laravel-discovery/src/Event/EventDiscovery.php`                   | Discovers `#[EventHandler]`; infers event from parameter type                  |
| `packages/laravel-discovery/src/Router/RouteDiscovery.php`                  | Discovers HTTP-method attributes; applies decorators                           |
| `packages/laravel-discovery/src/Schedule/ScheduleDiscovery.php`             | `#[Singleton]`; registers via `Schedule::call()`                               |
| `packages/laravel-discovery/src/Schedule/Scheduled.php`                     | Repeatable attribute; `Cron\|Every\|\Closure` schedule                         |
| `packages/laravel-discovery/src/Schedule/Every.php`                         | Backed enum of intervals (Second…Year)                                         |
| `packages/laravel-discovery-graphql/src/RebingGraphQL/GraphQLDiscovery.php` | Discovers GraphQL types/queries/mutations and `#[Query]`/`#[Mutation]` actions |
| `packages/laravel-discovery-graphql/src/RebingGraphQL/AsActionField.php`    | Trait that adapts a `DiscoveredAction` into a Rebing `Field`                   |
| `packages/laravel-discovery-graphql/src/RebingGraphQL/Authorization.php`    | Injectable helper for authorizing from inside a resolver                       |
| `packages/laravel-discovery/config/discovery.php`                           | Package configuration                                                          |
| `tests/TestCase.php`                                                        | Base test class; registers package + GraphQL + Workbench providers             |

## Code Style & Standards

- PHP 8.5+ (uses closures-as-attribute-arguments in `#[Scheduled]` and asymmetric visibility / property hooks in
  `Action` / `Scheduled`)
- Linter: Laravel Pint (`composer lint`) — config in `pint.json`
- All files: `declare(strict_types=1);`
- Namespaces: PSR-4 under `NielsJanssen\Laravel\Discovery\*` and `NielsJanssen\Laravel\Discovery\RebingGraphQL\*`

## CI/CD

- **`.github/workflows/ci.yml`**: lint (Pint) → test (Pest on PHP 8.5) → `composer audit` → dependency review (PRs only)
- **`.github/workflows/release.yml`**: manual `workflow_dispatch` — **one package per run**, so the three packages
  version independently. Inputs: `package` (choice), `bump` (`patch`/`minor`/`major`/`alpha`/`beta`/`rc`, default
  `patch`) and an optional `version` that overrides the bump with an exact semver. The next version is computed from the
  package's highest existing `<package>@v*` tag by `.github/scripts/bump-version.sh` (npm `semver inc` semantics: a
  prerelease whose base is already the target promotes to stable, so `1.0.0-beta.8` + `patch` → `1.0.0`; channels number
  from `.1`). Prereleases are marked as such on GitHub automatically. The workflow then regenerates
  `packages/<package>/CHANGELOG.md` via `git-cliff` (scoped with `--include-path` and `--tag-pattern`), commits it, and
  tags `<package>@vX.Y.Z`
- `.github/scripts/bump-version.test.sh` covers the bump table and runs in the CI lint job
- **`.github/workflows/split.yml`**: a push to `main` mirrors all three packages to their split repos; a
  `<package>@vX.Y.Z` tag only splits that one package, and the split repo receives the bare `vX.Y.Z` tag
- Cross-package constraints are **not** bumped automatically — `laravel-discovery-graphql` requires
  `nielsjanssen/laravel-discovery` by constraint, so a major of the core package needs that `require` edited by hand
  before releasing the dependent
