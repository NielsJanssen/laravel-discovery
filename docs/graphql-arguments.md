# GraphQL argument validation and hydration

Two hooks let you decide how a `#[Query]`/`#[Mutation]` method's parameters are validated, and how a
parameter receives a typed object built from the arguments. Both are plain interfaces registered with
container tags, so you are not tied to any particular library — including ours.

Both live in `NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument`:

- **`RuleProvider`** — contributes validation rules and messages for an action's arguments.
- **`Hydrator`** — turns the arguments into an object for a parameter.

An adapter may implement either or both.

## What ships by default

| Adapter | Hook | Active when |
|---|---|---|
| `ComposedFromArgsHydrator` | `Hydrator` | always |
| `LaravelValidationRules` | `RuleProvider` | `nielsjanssen/laravel-validation` is installed |

`nielsjanssen/laravel-validation` is a **suggestion, not a requirement**. Without it you simply have
one fewer rules provider, and `#[Arg(rules: [...])]` keeps working exactly as before.

## Validating with attributes

With `nielsjanssen/laravel-validation` installed, its attributes work on action parameters:

```php
use NielsJanssen\Laravel\Validation\Rule\{Each, Min, Max, Rule};

class BookQueries
{
    #[Query(type: 'Book', list: true)]
    public function books(
        #[Min(2), Max(255)] ?string $title = null,
        #[Rule('min:1', message: 'Pick at least one shelf.')] int $shelves = 1,
        #[Each('email')] array $notify = [],
    ): array {
        // ...
    }
}
```

Rules are keyed by **GraphQL arg name**, so `#[Arg('nickname')] string $name` reports under
`nickname`. `#[Each]` validates every element at its own path, so a bad third recipient is reported
as `notify.2` rather than as a failure of the whole list.

Parameter type inference applies as it does anywhere else in that package: a nullable parameter adds
`nullable`, and a parameter with a **default value adds `sometimes`** — which is what makes an
omitted optional argument pass rather than fail a rule it was never given a value for.

`#[Arg(rules:)]` and validation attributes on the same parameter **both apply**; they merge rather
than one replacing the other.

## Writing your own rules provider

```php
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\{ArgumentRules, RuleProvider};
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredAction;

final class SpatieDataRules implements RuleProvider
{
    public function rulesFor(DiscoveredAction $action, array $args): ArgumentRules
    {
        $rules = [];

        foreach ($action->argCompositions as $dataClass) {
            $rules = [...$rules, ...$dataClass::getValidationRules($args)];
        }

        return new ArgumentRules($rules);
    }
}
```

Register it in a service provider:

```php
$this->app->tag([SpatieDataRules::class], RuleProvider::TAG);
```

Every tagged provider is asked, and rules for the same argument accumulate, so several can coexist.
Whatever they return is merged **on top of** Rebing's own arg-level rules, which includes
`#[Arg(rules:)]` and the automatic `exists` rule on a model binding — a provider can add to those but
never silently replaces them.

`DiscoveredAction` gives you what you need to speak the boundary's language:

| Member | Use |
|---|---|
| `$class`, `$method` | reflect the action |
| `$args` | each `DiscoveredArg` carries both `$name` (GraphQL) and `$paramName` (PHP) |
| `$argCompositions` | `paramName => class-string` for hydrated parameters |
| `toParameters(array $args)` | re-key request args by parameter name |
| `toArgPath(string $paramPath)` | translate a parameter path back to an arg path, keeping trailing segments |

## Writing your own hydrator

A hydrator lets a resolver take a value object instead of a fistful of scalars. The arguments it is
built from are declared by an `ActionArgProvider`; the hydrator only has to build the object.

```php
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\Hydrator;
use Spatie\LaravelData\Data;

final class SpatieDataHydrator implements Hydrator
{
    public function hydrates(string $class): bool
    {
        return is_a($class, Data::class, allow_string: true);
    }

    public function hydrate(string $class, array $args): object
    {
        return $class::from($args);
    }
}
```

```php
$this->app->tag([SpatieDataHydrator::class], Hydrator::TAG);
```

`hydrates()` is asked at **discovery** time, so it must decide from the class name alone — it decides
whether the parameter becomes a hydrated value object or an ordinary container injection. The first
tagged hydrator to claim a class wins, so your own registration takes precedence over the built-in
one for a class both would handle.

Our own `Argument\ComposedFromArgs` is served by one such hydrator and holds no privileged position:

```php
final readonly class Pagination implements ComposedFromArgs
{
    public function __construct(public int $page = 1, public int $limit = 20) {}

    public static function fromArgs(array $args): static
    {
        return new static(page: $args['page'] ?? 1, limit: $args['limit'] ?? 20);
    }
}
```

## Validating a hydrated value object

A value object's properties are named after the flat arguments that feed it, so rules for them key
straight onto those argument names with no prefix:

```php
final readonly class Pagination implements ComposedFromArgs
{
    public function __construct(
        #[Min(1)] public int $page = 1,      // reported as `page`
        #[Min(1)] public int $limit = 20,    // reported as `limit`
    ) {}
}
```

`LaravelValidationRules` does this for every entry in `$action->argCompositions`, which is why
hydration and validation compose without either hook knowing about the other.
