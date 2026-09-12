# GraphQL authorization

`#[Authorize]` guards a query or mutation. Rebing calls it before validation runs, so an unauthorized caller cannot read
the validation rules to map out the API.

```php
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

#[Authorize]
class Orders
{
    #[Query(type: 'Order', list: true)]
    public function orders(): array
    {
        // only reached by an authenticated caller
    }
}
```

The attribute is repeatable and targets classes, methods, and parameters. Attributes are collected class-first, then
method, then parameter, and all of them must pass.

| Parameter | Type      | Purpose                                                                          |
|-----------|-----------|-----------------------------------------------------------------------------------|
| `ability` | `?string` | A Gate ability, checked against the model a parameter binds. Parameters only.     |
| `gate`    | `?string` | A class implementing `AuthorizationGate`. Classes and methods only.               |
| `message` | `?string` | The message reported when this check fails.                                       |

## Requiring a signed-in caller

A bare `#[Authorize]` checks `auth()->check()`, independently of whether Rebing's
`AddAuthUserContextValueMiddleware` is enabled. A failure reports Rebing's `Unauthorized` unless you set `message:`.

```php
#[Query(type: 'Invoice', list: true)]
#[Authorize(message: 'Sign in to view invoices.')]
public function invoices(): array
{
    // ...
}
```

## Authorizing a bound model

`#[Authorize('ability')]` on a model-bound parameter checks that ability against the record the parameter binds.

```php
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;

#[Query(type: 'Order')]
public function order(
    #[Arg('id')]
    #[Authorize('view')]
    Order $order,
): Order {
    return $order;   // OrderPolicy::view($user, $order) has already passed
}
```

Repeat the attribute to require several abilities on the same record. A failure reports `Forbidden` rather than
`Unauthorized`, since the caller is authenticated but not allowed, and `message:` overrides that.

Nullability decides what a missing record means.

| Binding                  | Record not found                        | Why                                                                        |
|--------------------------|-----------------------------------------|-----------------------------------------------------------------------------|
| `Order $order`           | denied, same error as "not allowed"     | A refusal never confirms whether the record exists.                         |
| `?Order $order = null`   | check skipped, resolver receives `null` | The signature already says absence is a valid outcome, so the field is null. |

A record that is found is always held to its ability, nullable or not.

Because the check runs before validation, the model is loaded once for the check and again when the resolver runs. That
is the cost of not leaking existence through validation errors.

The validation rule `#[Can]` from `nielsjanssen/laravel-validation` cannot do this job: validation only ever sees the
raw `ID` argument, so the Gate receives an id string where the policy expects a record. Discovery rejects that
combination with a `LogicException` pointing at `#[Authorize]`, along with a parameter `#[Authorize]` that carries no
ability, `#[Authorize(gate:)]` on a parameter, and an ability on a parameter that binds no model.

## Custom gates

For a check that needs the arguments, the context, or its own dependencies, point `gate:` at a class implementing
`AuthorizationGate`. The gate is resolved from the container, so constructor injection works.

```php
use GraphQL\Type\Definition\ResolveInfo;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\AuthorizationGate;

final class WithinOfficeHours implements AuthorizationGate
{
    public function __construct(private readonly Clock $clock) {}

    public function check(mixed $root, array $args, mixed $context, ?ResolveInfo $info): bool
    {
        return $this->clock->now()->setTimezone('Europe/Amsterdam')->hour < 18;
    }
}
```

```php
#[Query(type: 'Report', list: true)]
#[Authorize(gate: WithinOfficeHours::class, message: 'Reports are available until 18:00.')]
public function reports(): array
{
    // ...
}
```

## Authorizing inside a resolver

Type-hint `Authorization` to authorize from inside the method, for checks the attribute cannot express: a subject
computed in the body, a branch that guards only part of the work, or a second ability once the first has passed.

```php
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorization;

#[Query(type: 'Report')]
public function report(#[Arg('id')] Dossier $dossier, Authorization $auth): Report
{
    $auth->authorize('view', $dossier);

    $report = $dossier->report();

    if ($report->containsPersonalData()) {
        $auth->authorize('view-personal-data', $dossier, message: 'Not cleared for personal data.');
    }

    return $report;
}
```

`authorize(string|iterable|\UnitEnum $abilities, mixed $arguments = [], ?string $message = null)` delegates to
`Gate::denies()`, so a list requires every ability and enum abilities resolve through Laravel's `enum_value()`. On
failure it throws Rebing's `AuthorizationError`, which is client-safe and carries the `authorization` category,
defaulting to the same `Forbidden` as the attribute.

Use `Authorization` rather than Laravel's `Gate::authorize()` here. Laravel's `AuthorizationException` is not
client-safe in this pipeline and surfaces to the caller as an internal server error.

The helper is stateless and injectable either way: `#[Authorize]` on the class or method provides it as a value object,
and without that attribute it resolves from the container. It never appears as a GraphQL argument.

## Which one to reach for

Prefer `#[Authorize('ability')]` on the parameter when it fits, since it runs before validation and keeps the guard next
to the thing being guarded. Use a gate class when the decision needs the raw arguments or the context, and
`Authorization` when the decision depends on work the resolver has to do first.

## Where to next

- [GraphQL](graphql.md): queries, mutations, arguments, and model binding.
- [GraphQL argument validation and hydration](graphql-arguments.md): the rules and hydration hooks.
